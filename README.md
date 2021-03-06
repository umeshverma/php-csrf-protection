# PHP CSRF Protection Example

* CSRF token expiration.
* Automatic CSRF protection without code modification. i.e. CSRF unprotected PHP app can be protected w/o code modification. Use 'auto_prepend_file' for this.
* Recommended: PHP 7.1 or up. hash_hkdf() could be written by hash_hmac(), but output_rewrite_var() has poor implementation PHP 7.0 and blow.

## How it behaves

Cryptographicaly strong CSRF tokens with expiration are generated for each URI automatically.

* It automatically start session if session is not active.
* It automatically add CSRF token to web page output.
* CSRF token expires with configured expiration.
* CSRF token is updated with configured duration.
* FORMs will have hidden 'csrftk' input automatically.
* URLs for your site will have 'csrftk' parameter automatically.
* When CSRF validation is failed, it raises RuntimeException.

## How to use

Execute script like below for requests. You may use "auto_prepend_file"
for this purpose.

```php
try {
    // Update url_rewriter.tags to enable href rewrite.
    ini_set('url_rewriter.tags', 'form=,a=href');
    // Set up config values
    $GLOBALS['_CSRF_DISABLE_'] = false;
    $GLOBALS['_CSRF_EXPIRE_']  = 60; // 60 sec expiration
    $GLOBALS['_CSRF_RENEW_']   = 55; // 55 sec renewal before expiration
    $GLOBALS['_CSRF_SESSION_'] = true; // Use dedicated session for CSRF
    require_once(__DIR__.'/../src/csrf_init.php');
} catch (Exception $e) {
    // Show nice CSRF token error message for production use.
    echo '<a href="'.  csrf_get_uri() .'">Click here to return page<a><br />';
    echo $e->getMessage();
    exit;
}
```

## How to test drive

Clone repository and change directory to 'php-csrf-protection/example', then start PHP CLI server like;

```
$ php -S 127.0.0.1:8888
```

Access test script.

http://127.0.0.1:8888/csrf_test.php

First access will raise CSRF exception. Press "Click here to return page" link and there are tokens.

Live test script is here:

https://sample.ohgaki.net/php-csrf-protection/example/csrf_test.php


## FAQ

### Any security notices?

Since it adds CSRF tokens to "href", HTTP_REFERER should be disabled to protect tokens.

https://w3c.github.io/webappsec-referrer-policy/
https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Referrer-Policy

Chrome and Firefox support "Referrer-Policy". HTTPS can be used also.

It does not consider query parameters to generate CSRF token as well as does not provide different tokens for HTTP methods. It is better to include query parameters and HTTP methods for CSRF token generation. You need to add tokens manually in order to assign distinguished CSRF tokens.

csrf_validate_token()/csrf_generate_token() supports $extra_info parameters. You may pass $extra_info something like;

```php
// Generating token
$query_string = http_build_query($query_array);
$extra_info = ("GET\0".hash('sha256', $query_string));
$token = csrf_generate_token($secret, 3600, $extra_info);
// Validating token
unset($_GET['csrftk']);
$query_string = http_build_query($_GET);
$extra_info = ("GET\0".hash('sha256', $query_string));
$valid = csrf_generate_token($secret, $_GET['csrftk'], $extra_info);
if ($valid !=== ture) {
    throw new RuntimeException('CSRF validation error');
}
```

### HTML links do not have CSRF tokens

Since PHP 7.1.0, output rewriter uses its own output buffer. output_rewrite_var() only rewrites HTML form. To add CSRF token value to HTML links, change ini setting as follows;

```php
ini_set('url_rewriter.tags', 'form=,a=href');
```

### CSRF token is not added to page

This script uses output buffer. Therefore outputs already sent cannot be rewritten. Include 'csrf_init.php' before you start output anything.

Have fun!!
