<?php

namespace Acms\Services\Session;

class Engine
{
    protected $sessionName = '';

    protected $lifetime = 0;

    protected $sessionLifetime = 60 * 60 * 24 * 90;

    protected $path = '';

    protected $domain = '';

    protected $secure = false;

    protected $httpOnly = false;

    protected $sameSite = 'None'; // None | Lax | Strict

    protected $storage = [];

    protected $storageName = 'acms_storage';

    /**
     * Constructor
     */
    public function __construct($sessionName, $config)
    {
        $this->sessionName = $sessionName;
        foreach ($config as $key => $val) {
            if (!property_exists($this, $key)) {
                continue;
            }
            $this->$key = $val; // @phpstan-ignore-line
        }
        if (defined('PHP_SESSION_USE_DB') && PHP_SESSION_USE_DB) {
            $this->useDatabase();
        }
        $this->lifetime = intval($this->lifetime);

        // セッションスタート
        $this->start();

        // 有効期限チェック
        if (isset($_SESSION['lifetime']) && $_SESSION['lifetime'] < REQUEST_TIME) {
            $this->destroy(); // 有効期限切れ
            $this->start(); // セッション再スタート
        } else {
            $this->storage = isset($_SESSION[$this->storageName]) ? acmsUnserialize($_SESSION[$this->storageName]) : []; // セッション変数から，前回の状態を取得
        }
        if (!isset($_SESSION['lifetime'])) {
            if ($this->lifetime === 0) {
                $_SESSION['lifetime'] = REQUEST_TIME + $this->sessionLifetime;
            } else {
                $_SESSION['lifetime'] = REQUEST_TIME + $this->lifetime;
            }
        }
    }

    public function handle()
    {
        return $this;
    }

    public function getSessionId()
    {
        return session_id();
    }

    public function writeClose()
    {
        session_write_close();
    }

    /**
     * Regenerate session.
     */
    public function regenerate()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Save
     *
     * @return void
     */
    public function save()
    {
        $_SESSION[$this->storageName] = acmsSerialize($this->storage);
    }

    /**
     * Get
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get($key)
    {
        return isset($this->storage[$key]) ? $this->storage[$key] : false;
    }

    /**
     * Set
     *
     * @param string $key
     * @param mixed $val
     *
     * @return void
     */
    public function set($key, $val)
    {
        $this->storage[$key] = $val;
    }

    /**
     * Delete
     *
     * @param string $key
     *
     * @return void
     */
    public function delete($key)
    {
        unset($this->storage[$key]);
    }

    /**
     * Clear
     *
     * @return void
     */
    public function clear()
    {
        $this->storage = null;
        $this->save();
    }

    /**
     * Session start.
     *
     * @return void
     */
    protected function start()
    {
        session_name($this->sessionName);
        session_set_cookie_params([
            'lifetime' => $this->lifetime,
            'path' => $this->path,
            'domain' => $this->domain,
            'secure' => $this->secure,
            'httponly' => $this->httpOnly,
            'samesite' => $this->sameSite,
        ]);
        session_cache_limiter(null); // HTTPヘッダーは独自で設定する
        session_start();
    }

    /**
     * Destroy session.
     */
    public function destroy()
    {
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params["path"],
            'domain' =>  $params["domain"],
            'secure' => $params["secure"],
            'httponly' => $params["httponly"],
            'samesite' => $params["samesite"],
        ]);
        session_destroy();
    }

    /**
     *  セッション時間の延長
     */
    public function extendExpires()
    {
        $expires = $this->lifetime === 0 ? 0 : REQUEST_TIME + $this->lifetime;

        if ($this->lifetime === 0) {
            $_SESSION['lifetime'] = REQUEST_TIME + $this->sessionLifetime;
        } else {
            $_SESSION['lifetime'] = $expires;
        }
        $params = session_get_cookie_params();
        setcookie($this->sessionName, $_COOKIE[$this->sessionName], [
            'expires' => $expires,
            'path' => $params['path'],
            'domain' =>  $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
    }

    /**
     * セッションデータをCookieではなくDBに保存
     */
    protected function useDatabase()
    {
        $handler = new DatabaseHandler();
        session_set_save_handler($handler, true);
    }
}
