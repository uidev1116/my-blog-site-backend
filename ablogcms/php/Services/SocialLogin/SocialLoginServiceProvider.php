<?php

namespace Acms\Services\SocialLogin;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;
use Acms\Services\Facades\Config;

class SocialLoginServiceProvider extends ServiceProvider
{
    /**
     * register service
     *
     * @param \Acms\Services\Container $container
     *
     * @return void
     */
    public function register(Container $container)
    {
        $container->bind('google-login', function () {
            $config = Config::loadBlogConfigSet(BID);
            return new Google(
                $config->get('google_login_client_id'),
                $config->get('google_login_secret')
            );
        });

        $container->singleton('line-login', function () {
            $config = Config::loadBlogConfigSet(BID);
            return new Line(
                $config->get('line_app_id'),
                $config->get('line_app_secret')
            );
        });

        $container->singleton('twitter-login', function () {
            $config = Config::loadBlogConfigSet(BID);
            return new Twitter(
                $config->get('twitter_sns_login_consumer_key'),
                $config->get('twitter_sns_login_consumer_secret')
            );
        });
    }

    /**
     * initialize service
     *
     * @return void
     */
    public function init()
    {
    }
}
