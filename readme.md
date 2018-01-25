# JSON-RPC Plugin for Kirby CMS

Install this plugin to be able to easily define an API based on [JSON-RPC](http://www.jsonrpc.org/specification) in your own Kirby plugins.

## Usage

For example, a procedure that returns all registered users could be set up as follows:

```php
kirby()->set('rpc', 'get_users', function () {
  return kirby()->site()->users()->toJson();
});
```

And this is how you would call it:

```json
{
    "jsonrpc" : "2.0",
    "method" : "get_users",
    "params" : []
}
```

As a second example, this is how you would define a procedure that adds two numbers:

```php
kirby()->set('rpc', 'add', function ($a, $b) {
  return $a + $b;
});
```

```json
{
    "jsonrpc" : "2.0",
    "method" : "add",
    "params" : [3, 4]
}
```
The response returned by the server will be:
```json
{
    "jsonrpc" : "2.0",
    "result" : 7
}
```

## Installation

Just copy the plugin directory into `app/site/plugins` and make sure it is called `amazing_jsonrpc`.


