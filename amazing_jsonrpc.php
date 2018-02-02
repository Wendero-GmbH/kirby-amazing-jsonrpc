<?php

namespace Kirby\Registry {
  use Kirby;
  use Kirby\Registry;

  class Rpc extends Entry {
    public function set(Array $conf) {
      $this->kirby->rpc->add($conf);
    }
  }
}

namespace JsonRpc {

  class RpcMethod {
    public $function;
    public $rolesWhitelist = [];
    public function __construct(callable $function, Array $roles = []) {
      $this->function = $function;
      $this->rolesWhitelist = $roles;
    }
    public function allowed(\User $user) {
      return size($this->rolesWhitelist) === 0
          || in_array($user->role(), $this->rolesWhitelist);
    }
  }

  class RpcDispatcher {

    const JSON_RPC_VERSION = '2.0';
    const JSON_PARSE_ERROR = -32700;
    const INVALID_REQUEST = -32600;
    const METHOD_NOT_FOUND = -32601;
    const INVALID_PARAMS = -32602;
    const INTERNAL_ERROR = -32603;

    private $methods = [];

    /**
     * @param Kirby $kirby
     */
    public function __construct(\Kirby $kirby) {
      $this->methods = [];

      $kirby->set('route', [
        'pattern' => 'jsonrpc',
        'method' => 'POST',
        'action' => [$this, 'onMessage']
      ]);
    }

    /**
     * @param int $code
     * @param string $message
     * @return \Response
     */
    private static function error($code, $message) {

      if (!is_int($code)) throw new \Exception('code must be int');

      $args = array_slice(func_get_args(), 1);

      return new \Response([
        'error' => [
          'message' => call_user_func_array('sprintf', $args),
          'code' => $code
        ]
      ], 'json', 504);
    }

    /**
     * @return \Response
     */
    public function onMessage() {

      $payloadRaw = file_get_contents('php://input');

      $payload = json_decode($payloadRaw, true);

      if (is_null($payload) || !is_array($payload)) {
        return self::error(self::JSON_PARSE_ERROR, 'unable to parse json');
      }

      if (!array_key_exists('id', $payload)) {
        return self::error(0, 'missing id');
      }

      $id = $payload['id'];

      if (!is_int($id) && !is_null($id)) {
        return self::error(0, 'id must be either null or an integer');
      }

      $version = $payload['jsonrpc'];

      if ($version !== self::JSON_RPC_VERSION) {
        return self::error(0, 'jsonrpc must be %s, was %s', self::JSON_RPC_VERSION, $version);
      }

      $method = $payload['method'];

      if (!array_key_exists($method, $this->methods)) {
        return self::error(self::METHOD_NOT_FOUND, 'method %s is missing', $method);
      }

      $cb = $this->methods[$method];

      $info = new \ReflectionFunction($cb->function);

      $nargs = $info->getNumberOfRequiredParameters();

      if (!array_key_exists('params', $payload)) {
        return self::error(0, 'missing params');
      }

      $params = $payload['params'];

      if (!is_array($params) || $nargs !== count($params)) {
        return self::error(self::INVALID_PARAMS, 'Method expects %d params, but got %d', $nargs, count($params));
      }

      try {

        if ($cb->allowed(kirby()->site()->user())) {

          $r = call_user_func_array($cb->function, $params);

          return new \Response([
            'result' => $r,
            'jsonrpc' => self::JSON_RPC_VERSION,
            'id' => $id
          ], 'json');


        }
        else {
          return new \Response([
            'error' => 'not authorized'
          ], 'json', 501);
        }
      }
      catch (Exception $e) {
        return self::error(self::INTERNAL_ERROR, 'error :(');
      }

    }

    /**
     * @param Array $conf
     */
    public function add(Array $conf) {

      if (!array_key_exists('method', $conf)) {
        throw new \Exception('missing method');
      }

      if (!is_string($conf['method'])) {
        throw new \Exception('method name must be string');
      }

      $name = $conf['method'];

      $callback = $conf['action'];

      if (array_key_exists($name, $this->methods)) {
        throw new \Exception("method name $name already taken");
      }

      $this->methods[$name] = new RpcMethod($callback);

      if (array_key_exists('roles', $conf)) {
        $this->methods[$name]->rolesWhitelist = $conf['roles'];
      }
    }
  }

  $kirby->rpc = new \JsonRpc\RpcDispatcher(kirby());  
}
