<?php
/**
 * Copyright (c) 2012 Aleph Tav
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated 
 * documentation files (the "Software"), to deal in the Software without restriction, including without limitation 
 * the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, 
 * and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO 
 * THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE 
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, 
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @author Aleph Tav <4lephtav@gmail.com>
 * @link http://www.4leph.com
 * @copyright Copyright &copy; 2012 Aleph Tav
 * @license http://www.opensource.org/licenses/MIT
 */

namespace Aleph\Core;

use Aleph\MVC;

/**
 * IDelegate interface is intended for building of Aleph framework callbacks.
 * Using such classes we can transform strings in certain format into callback if necessary. 
 *
 * @author Aleph Tav <4lephtav@gmail.com>
 * @version 1.0.3
 * @package aleph.core
 */
interface IDelegate
{
  /**
   * Constructor. The only argument of it is string in Aleph framework format.
   * The following formats are possible:
   * 'function' - invokes a global function with name 'function'.
   * '->method' - invokes a method 'method' of Aleph\MVC\Page::$page object (if defined) or Aleph object. 
   * '::method' - invokes a static method 'method' of Aleph\MVC\Page::$page object (if defined) or Aleph object.
   * 'class::method' - invokes a static method 'method' of a class 'class'.
   * 'class->method' - invokes a method 'method' of a class 'class' with its constructor without arguments.
   * 'class[]' - creates an object of a class 'class' without sending any arguments in its constructor.
   * 'class[]::method' - the same as 'class::method'.
   * 'class[]->method' - the same as 'class->method'.
   * 'class[n]' - creates an object of a class 'class' with its constructor taking n arguments.
   * 'class[n]::method' - the same as 'class::method'.
   * 'class[n]->method' - invokes a method 'method' of a class 'class' with its constructor taking n arguments.
   * 'class@cid->method' - invokes a method 'method' of web control type of 'class' with unique (or logic) ID equals 'cid'.
   *
   * @param mixed $callback - an Aleph framework callback string or a callable callback.
   * @access public
   */
  public function __construct($callback);
  
  /**
   * The magic method allowing to invoke an object of this class as a method.
   * The method can take different number of arguments.
   *
   * @return mixed
   * @access public
   */
  public function __invoke();
  
  /**
   * The magic method allowing to convert an object of this class to a callback string.
   *
   * @return string
   * @access public
   */
  public function __toString();
  
  /**
   * Invokes a callback's method or creating a class object.
   * For callbacks in format 'class[n]' and 'class[n]->method' first n arguments of the method
   * are arguments of constructor of a class 'class'.
   *
   * @param array $args - array of method arguments.
   * @return mixed
   * @access public
   */
  public function call(array $args = null);
  
  /**
   * Checks whether it is possible according to permissions to invoke this delegate or not.
   * Permission can be in the following formats:
   * - class name with method name: class::method, class->method
   * - class name without any methods.
   * - function name.
   * - namespace.
   * The method returns TRUE if a callback matches with one or more permissions and FALSE otherwise.
   *
   * @param string | array $permissions - permissions to check.
   * @return boolean
   * @access public
   */
  public function in($permissions);
  
  /**
   * Verifies that the delegate exists and can be invoked.
   *
   * @param boolean $autoload - whether or not to call __autoload by default.
   * @return boolean
   * @access public
   */
  public function isCallable($autoload = true);
  
  /**
   * Returns parameters of a delegate class method, function or closure. 
   * Parameters returns as an array of ReflectionParameter class instance.
   *
   * @return \ReflectionParameter
   * @access public
   */
  public function getParameters();
  
  /**
   * Returns array of detail information of the callback.
   * Output array has the format ['class' => ... [string] ..., 
   *                              'method' => ... [string] ..., 
   *                              'static' => ... [boolean] ..., 
   *                              'numargs' => ... [integer] ..., 
   *                              'cid' => ... [string] ...,
   *                              'type' => ... [string] ...]
   *
   * @return array
   * @access public
   */
  public function getInfo();
  
  /**
   * Returns full class name (with namespace) of the callback.
   *
   * @return string
   * @access public
   */
  public function getClass();
  
  /**
   * Returns method name of the callback.
   *
   * @return string
   * @access public
   */
  public function getMethod();
  
  /**
   * Returns callback type. Possible values can be "closure", "function", "class" or "control".
   *
   * @return string
   * @access public
   */
  public function getType();
  
  /**
   * Creates and returns object of callback's class.
   *
   * @param array $args - arguments of the class constructor.
   * @return object
   * @access public
   */
  public function getClassObject(array $args = null);
}

/**
 * With this class you can transform a string in certain format into a method or function invoking.
 *
 * @author Aleph Tav <4lephtav@gmail.com>
 * @version 1.0.0
 * @package aleph.core
 */
class Delegate implements IDelegate
{
  // Error message template.
  const ERR_DELEGATE_1 = 'Callback is not callable.';
  const ERR_DELEGATE_2 = 'Control with UID = "[{var}]" is not found.';

  /**
   * A string in the Aleph callback format.
   *
   * @var string $callback
   * @access protected
   */
  protected $callback = null;
  
  /**
   * Full name (class name with namespace) of a callback class.
   *
   * @var string $class
   * @access protected
   */
  protected $class = null;
  
  /**
   * Method name of a callback class.
   *
   * @var string $method
   * @access protected
   */
  protected $method = null;
  
  /**
   * Equals TRUE if a callback method is static and FALSE if it isn't.
   *
   * @var boolean $static
   * @access protected
   */
  protected $static = null;
  
  /**
   * Shows number of callback arguments that should be transmited into callback constructor.
   *
   * @var integer $numargs
   * @access protected
   */
  protected $numargs = null;
  
  /**
   * Contains logic identifier or unique identifier of a web-control.
   *
   * @var string $cid
   * @access protected
   */
  protected $cid = null;
  
  /**
   * Can be equal 'function', 'closure' or 'class' according to callback format.
   *
   * @var string $type
   * @access protected
   */
  protected $type = null;

  /**
   * Constructor. The only argument of it is string in Aleph framework format.
   * The following formats are possible:
   * 'function' - invokes a global function with name 'function'.
   * '->method' - invokes a method 'method' of Aleph\MVC\Page::$page object (if defined) or Aleph object. 
   * '::method' - invokes a static method 'method' of Aleph\MVC\Page::$page object (if defined) or Aleph object.
   * 'class::method' - invokes a static method 'method' of a class 'class'.
   * 'class->method' - invokes a method 'method' of a class 'class' with its constructor without arguments.
   * 'class[]' - creates an object of a class 'class' without sending any arguments in its constructor.
   * 'class[]::method' - the same as 'class::method'.
   * 'class[]->method' - the same as 'class->method'.
   * 'class[n]' - creates an object of a class 'class' with its constructor taking n arguments.
   * 'class[n]::method' - the same as 'class::method'.
   * 'class[n]->method' - invokes a method 'method' of a class 'class' with its constructor taking n arguments.
   * 'class@cid->method' - invokes a method 'method' of web control type of 'class' with unique (or logic) ID equals 'cid'.
   *
   * @param mixed $callback - an Aleph framework callback string or a callable callback.
   * @access public
   */
  public function __construct($callback)
  {
    if ($callback instanceof IDelegate)
    {
      foreach ($callback->getInfo() as $var => $value) $this->{$var} = $value;
      $this->callback = (string)$callback;
    }
    else if ($callback instanceof \Closure)
    {
      $this->type = 'closure';
      $this->method = $callback;
      $this->callback = 'Closure';
    }
    else if (is_object($callback))
    {
      $class = new \ReflectionClass($callback);
      $this->type = 'class';
      $this->class = $callback;
      $this->method = '__construct';
      $this->numargs = $class->hasMethod($this->method) ? $class->getConstructor()->getNumberOfParameters() : 0;
      $this->static = false;
      $this->callback = get_class($callback) . '[' . ($this->numargs ?: '') . ']';
    }
    else if (is_array($callback))
    {
      if (!is_callable($callback, true)) throw new Exception($this, 'ERR_DELEGATE_1');
      $this->type = 'class';
      $this->class = $callback[0];
      $this->method = $callback[1];
      $this->static = !is_object($this->class);
      $this->numargs = $this->static ? 0 : (new \ReflectionClass($this->class))->getConstructor()->getNumberOfParameters();
      $this->callback = (is_object($this->class) ? get_class($this->class) : $this->class) . ($this->static ? '::' : '[' . ($this->numargs ?: ''). ']->') . $this->method;
    }
    else
    {
      if ($callback == '' || is_numeric($callback)) throw new Exception($this, 'ERR_DELEGATE_1');
      $callback = htmlspecialchars_decode($callback);
      preg_match('/^([^\[:-]*)(\[([^\]]*)\])?(::|->)?([^:-]*)$/', $callback, $matches);
      if ($matches[4] == '' && $matches[2] == '')
      {
        $this->type = 'function';
        $this->method = $matches[1];
      }
      else
      {
        $this->type = 'class';
        $this->class = $matches[1] ?: (MVC\Page::$page instanceof MVC\IPage ? get_class(MVC\Page::$page) : 'Aleph');
        if ($this->class[0] == '\\') $this->class = ltrim($this->class, '\\');
        $this->numargs = (int)$matches[3];
        $this->static = ($matches[4] == '::');
        $this->method = $matches[5] ?: '__construct';
        $class = explode('@', $this->class);
        if (count($class) > 1)
        {
          $this->class = $class[0];
          $this->cid = $class[1];
          $this->type = 'control';
        }
      }
      $this->callback = $callback;
    }
  } 
  
  /**
   * The magic method allowing to convert an object of this class to a callback string.
   *
   * @return string
   * @access public
   */
  public function __toString()
  {
    return $this->callback;
  }
  
  /**
   * The magic method allowing to invoke an object of this class as a method.
   * The method can take different number of arguments.
   *
   * @return mixed
   * @access public
   */
  public function __invoke()
  {
    return $this->call(func_get_args());
  }
  
  /**
   * Invokes a callback's method or creating a class object.
   * For callbacks in format 'class[n]' and 'class[n]->method' first n arguments of the method
   * are arguments of constructor of a class 'class'.
   *
   * @param array $args - array of method arguments.
   * @return mixed
   * @access public
   */
  public function call(array $args = null)
  {
    $args = (array)$args;
    if ($this->type == 'function' || $this->type == 'closure') return call_user_func_array($this->method, $args);
    if ($this->type == 'control')
    {
      if (($class = MVC\Page::$page->get($this->cid)) === false) throw new Core\Exception($this, 'ERR_DELEGATE_2', $this->cid);
      if ($this->method == '__construct') return $class;
      return call_user_func_array([$class, $this->method], $args);
    }
    else
    {
      if ($this->static) return call_user_func_array([$this->class, $this->method], $args);
      if (is_object($this->class)) $class = $this->class;
      else if ($this->class == 'Aleph') $class = \Aleph::getInstance();
      else if (MVC\Page::$page instanceof MVC\IPage && $this->class == get_class(MVC\Page::$page)) $class = MVC\Page::$page;
      else $class = $this->getClassObject($args);
      if ($this->method == '__construct') return $class;
      return call_user_func_array([$class, $this->method], $args);
    }
  }

  /**
   * Checks whether it is possible according to permissions to invoke this delegate or not.
   * Permission can be in the following formats:
   * - class name with method name: class::method, class->method
   * - class name without any methods.
   * - function name.
   * - namespace.
   * The method returns TRUE if a callback matches with one or more permissions and FALSE otherwise.
   *
   * @param string | array $permissions - permissions to check.
   * @return boolean
   * @access public
   */
  public function in($permissions)
  {
    if ($this->type == 'closure') return true;
    if ($this->type == 'function')
    {
      $m = $this->split($this->method);
      foreach ((array)$permissions as $permission)
      {
        $p = $this->split($permission);
        if ($p[0] != '' && $m == $p || $p[0] == '' && substr($m[1], 0, strlen($p[1])) == $p[1]) return true;
      }
    }
    else
    {
      $m = $this->split(is_object($this->class) ? get_class($this->class) : $this->class);
      foreach ((array)$permissions as $permission)
      {
        $info = explode($this->static ? '::' : '->', $permission);
        if (!empty($info[1]) && $info[1] != $this->method) continue;
        $p = $this->split($info[0]);
        if ($p[0] != '' && $m == $p || $p[0] == '' && substr($m[1], 0, strlen($p[1])) == $p[1]) return true;
      }
    }
    return false;
  }
  
  /**
   * Verifies that the delegate exists and can be invoked.
   *
   * @param boolean $autoload - whether or not to call __autoload by default.
   * @return boolean
   * @access public
   */
  public function isCallable($autoload = true)
  {
    if ($this->type == 'closure') return true;
    if ($this->type == 'function') return function_exists($this->method);
    if ($this->type == 'control') return MVC\Page::$page->get($this->cid) !== false;
    $static = $this->static;
    $methodExists = function($class, $method) use ($static)
    {
      if (!method_exists($class, $method)) return false;
      $class = new \ReflectionClass($class);
      if (!$class->hasMethod($method)) return false;
      $method = $class->getMethod($method);
      return $method->isPublic() && $static === $method->isStatic();
    };
    if (is_object($this->class) || class_exists($this->class, false)) return $methodExists($this->class, $this->method);
    if (!$autoload) return false;
    if (!\Aleph::getInstance()->load($this->class)) return false;
    return $methodExists($this->class, $this->method);
  }
  
  /**
   * Returns parameters of a delegate class method, function or closure. 
   * Method returns FALSE if class method doesn't exist.
   * Parameters are returned as an array of ReflectionParameter class instance.
   *
   * @return \ReflectionParameter | boolean
   * @access public
   */
  public function getParameters()
  {
    if ($this->type != 'class') return (new \ReflectionFunction($this->method))->getParameters();
    $class = new \ReflectionClass($this->class);
    if (!$class->hasMethod($this->method)) return false;
    return $class->getMethod($this->method)->getParameters();
  }
  
  /**
   * Returns array of detail information of the callback.
   * Output array has the format ['class' => ... [string] ..., 
   *                              'method' => ... [string] ..., 
   *                              'static' => ... [boolean] ..., 
   *                              'numargs' => ... [integer] ..., 
   *                              'cid' => ... [string] ...,
   *                              'type' => ... [string] ...]
   *
   * @return array
   * @access public
   */
  public function getInfo()
  {
    return ['class' => $this->class, 
            'method' => $this->method,
            'static' => $this->static,
            'numargs' => $this->numargs,
            'cid' => $this->cid,
            'type' => $this->type];
  }
  
  /**
   * Returns full class name (with namespace) of the callback.
   *
   * @return string
   * @access public
   */
  public function getClass()
  {
    return $this->class;
  }
  
  /**
   * Returns method name of the callback.
   *
   * @return string
   * @access public
   */
  public function getMethod()
  {
    return $this->method;
  }
  
  /**
   * Returns callback type. Possible values can be "closure", "function", "class" or "control".
   *
   * @return string
   * @access public
   */
  public function getType()
  {
    return $this->type;
  }
  
  /**
   * Creates and returns object of callback's class.
   *
   * @param array $args - arguments of the class constructor.
   * @return object
   * @access public
   */
  public function getClassObject(array $args = null)
  {
    if (empty($this->class)) return;
    if ($this->type == 'control') return MVC\Page::$page->get($this->cid);
    $class = new \ReflectionClass($this->class);
    if ($this->numargs == 0) return $class->newInstance();
    return $class->newInstanceArgs(array_splice($args, 0, $this->numargs));
  }
  
  /**
   * Splits full class name on two part: namespace and proper class name. Method returns these parts as an array.
   *
   * @param string $identifier - full class name.
   * @return array
   * @access private
   */
  private function split($identifier)
  {
    if (strlen($identifier) == 0) return ['', ''];
    $ex = explode('\\', $identifier[0] == '\\' ? ltrim($identifier, '\\') : $identifier);
    return [array_pop($ex), implode('\\', $ex)];
  }
}