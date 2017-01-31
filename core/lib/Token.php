<?php

namespace ICT\Core;

/* * ****************************************************************
 * Copyright © 2014 ICT Innovations Pakistan All Rights Reserved   *
 * Developed By: Nasir Iqbal                                       *
 * Website : http://www.ictinnovations.com/                        *
 * Mail : nasir@ictinnovations.com                                 *
 * **************************************************************** */

use Twig_Environment;
use Twig_Loader_Filesystem;

class Token
{

  /** @const */
  const SOURCE_CONF = 1;
  const SOURCE_SESSION = 2;
  const SOURCE_HTTP = 4;
  const SOURCE_CUSTOM = 8;
  const SOURCE_ALL = 15;
  const REPLACE_EMPTY = 0;
  const KEEP_ORIGNAL = 1;

  /** @var Data $token */
  public $token = NULL;

  /** @var integer $default_value */
  public $default_value = Token::KEEP_ORIGNAL;

  /**
   * template path, if empty then global variable path_template will be used
   * @var string
   */
  public $template_dir = '';

  public function __construct($token_flag = Token::SOURCE_ALL, $data = array())
  {
    $this->token = new Data();
    if (Token::SOURCE_HTTP == ($token_flag & Token::SOURCE_HTTP)) {
      $this->token->merge(Http::get_instance());
    }
    if (Token::SOURCE_CONF == ($token_flag & Token::SOURCE_CONF)) {
      $this->token->merge(Conf::get_instance());
    }
    if (Token::SOURCE_SESSION == ($token_flag & Token::SOURCE_SESSION)) {
      $this->token->merge(Session::get_instance());
    }
    if (Token::SOURCE_CUSTOM == ($token_flag & Token::SOURCE_CUSTOM) && !empty($data)) {
      $newData = new Data($data);
      $this->token->merge($newData);
    }
  }

  public function add($name, &$value)
  {
    $this->token->{$name} = $value;
  }

  public function merge(Token $oToken)
  {
    $this->token->merge($oToken->token);
  }

  public function render_template($template, $default_value = Token::KEEP_ORIGNAL)
  {
    $template_dir = $this->template_dir;
    if (empty($this->template_dir)) {
      global $path_template;
      $template_dir = $path_template;
    }

    global $path_template; //, $path_cache;

    $this->default_value = $default_value;

    /* ---------------------------------------------- PREPARE TEMPLATE ENGINE */
    $loader = new Twig_Loader_Filesystem($template_dir); // template home dir
    $twig = new Twig_Environment($loader, array(
        'autoescape' => false,
            // uncomment following line to enable cache
            // 'cache' => $path_cache,
    ));

    /* ------------------------------------------------------ RENDER TEMPLATE */
    return $twig->render($template, $this->token->getDataCopy());
  }

  public function render_variable($variable, $default_value = Token::KEEP_ORIGNAL)
  {
    $this->default_value = $default_value;

    if (!empty($variable) && (is_array($variable) || is_object($variable))) {
      $result = array();
      foreach ($variable as $key => $value) {
        if (is_array($value) || is_object($variable)) {
          $result[$key] = $this->render_variable($value);
        } else {
          $result[$key] = $this->render_string($value);
        }
      }
      return $result;
    } else {
      return $this->render_string($variable);
    }
  }

  public function render_string($in_str)
  {
    // the most outer () is being used to get whole matched string in $macths[1] note matchs[0] have '[]'
    $regex = "/\[(([\w]+)(:[\w]+)*)\]/";
    return preg_replace_callback($regex, array($this, 'render_string_callback'), $in_str);
  }

  public function render_string_callback($org_matchs)
  {
    if (isset($this->token->{$org_matchs[0]})) {
      return $this->token->{$org_matchs[0]};
    } else if (Token::REPLACE_EMPTY === $this->default_value) {
      return ''; // replace with empty string, to remove it
    } else {
      return $org_matchs[0]; // if nothing above worked then return original
    }
  }

}