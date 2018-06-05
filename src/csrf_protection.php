<?php
// TEST & Usage
// $secret = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
// var_dump( $tk = csrf_generate_token($secret, 60, 'aaa') );
// var_dump(csrf_validate_token($secret, $tk, 'aaa'));


/**
 * Generate secure CSRF token
 *
 * @param string $secret Random secret string for key derivation.
 * @param int    $expire Expiration in seconds.
 * @param string $extra_info Extra info such as query parameters.
 *
 * @return string CSRF token.
 */
function csrf_generate_token($secret, $expire = 300, $extra_info = '')
{
    assert(is_string($secret) && strlen($secret) >= 32);
    assert(is_int($expire) && $expire >= 15);
    assert(is_string($extra_info));

    $salt = bin2hex(random_bytes(32));
    $expire += time();
    $key = bin2hex(hash_hkdf('sha256', $secret, 0, $extra_info."\0".$expire, $salt));
    $token = join("-", [$salt, $key, $expire]);
    assert(strlen($token) > 32);
    return $token;
}


/**
 * Validate CSRF token
 *
 * @param string $secret Random secret string for key derivation.
 * @param string $token  CSRF token generated by csrf_generate_token().
 * @param string $extra_info Optional extra info such as query parameters.
 *
 * @return bool or string  Returns TRUE for success, string error message for errors.
 */
function csrf_validate_token($secret, $token, $extra_info = '')
{
    assert(is_string($secret) && strlen($secret) >= 32);
    assert(is_string($token) && strlen($token) >= 32);
    assert(is_string($extra_info));

    if ($token === '') {
        return 'No token';
    }
    if (!is_string($token)) {
        return 'Attack - Non-string token';
    }
    $tmp = explode("-", $token);
    if (count($tmp) !== 3) {
        return 'Atatck - Invalid token';
    }
    list($salt, $key, $expire) = $tmp;
    if (empty($salt) || empty($key) || empty($expire)) {
        return 'Attack - Invalid token';
    }
    if (strlen($expire) != strspn($expire, '1234567890')) {
        return 'Attack - Invalid expire';
    }
    $key2 = bin2hex(hash_hkdf('sha256', $secret, 0, $extra_info."\0".$expire, $salt));
    if (hash_equals($key, $key2) === false) {
        return 'Attack - Key mismatch';
    }
    if ($expire < time()) {
        return 'Expired';
    }
    return true;
}


/**
 * Utility function that returns "csrftk" removed current URI.
 *
 * @return string URI string
 */
function csrf_get_uri($blacklist = [])
{
    assert(is_array($blacklise));
    // White list should be used, but it cannot be done universally.
    // Some get params can be dangerous, remove them.
    $g = $_GET ?? [];
    foreach($blacklist as $el) {
        unset($g[$el]);
    }
    unset($g['csrftk']);
    $q = http_build_query($g);
    $p = parse_url(($_SERVER['REQUEST_URI'] ?? ''));

    $uri = '';
    if (!empty($p['host'])) {
        $uri = '//'. $p['host'];
    }
    if (!empty($p['user'])) {
        $uri .= ':'. $p['user'];
    }
    if (!empty($p['pass'])) {
        $uri .= '@'. $p['pass'];
    }
    if (!empty($p['port'])) {
        $uri .= ':'. $p['port'];
    }
    if ($q) {
        $uri .= $p['path'] .'?'. $q;
    } else {
        $uri .= $p['path'];
    }
    if (!empty($p['fragment'])) {
        $uri .= '#'. $p['fragment'];
    }

    return $uri;
}


/**
 * Utility function that generates posted form.
 */
function csrf_get_form($opts = [], $blacklist = [])
{
    assert(is_array($opts));
    assert(is_array($blacklist));

    if (empty($_POST)) {
        return '';
    }
    unset($_POST['csrftk']);

    $opts['submit'] = $opts['submit'] ?? '<input type="submit" name="submit" value="Send posted data to server" />'.PHP_EOL;
    $opts['class']  = $opts['class'] ?? 'csrf_error';

    echo '<form method="post" action="'. csrf_get_uri($blacklist) .'" class="'. htmlspecialchars($opts['class']) .'">' .PHP_EOL;
    foreach($_POST as $key => $val) {
        echo '<input type="hidden" name="'. htmlspecialchars($key) .'" value="'. htmlspecialchars($val) .'" />' .PHP_EOL;
    }
    echo $opts['submit'] .PHP_EOL;
    echo '</form>' .PHP_EOL;
}