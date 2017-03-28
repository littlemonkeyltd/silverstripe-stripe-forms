<?php

/**
 * Config class to store common settings and functions
 * for this module
 *
 * @package StripeForms
 */
 class StripeForms extends ViewableData
 {

    /**
     * Publish key for the test account
     *
     * @var string
     * @config
     */
    private static $test_publish_key;

    /**
     * Publish key for the live account
     *
     * @var string
     * @config
     */
    private static $live_publish_key;

    /**
     * Publish key for the test account
     *
     * @var string
     * @config
     */
    private static $test_secret_key;

    /**
     * Publish key for the live account
     *
     * @var string
     * @config
     */
    private static $live_secret_key;

    /**
     * Config variable to specify if we want to use custom JS.
     * Enabling this will disable all requirements calls
     *
     * @var Boolean
     * @config
     */
    private static $use_custom_js = false;

    /**
     * Either get the publish key from config
     * check if a global constant has been set
     *
     * @return string
     */
    public static function publish_key()
    {
        if(Director::isDev()) {
            if (defined("STRIPE_TEST_PK")) {
                return STRIPE_TEST_PK;
            } else {
                return self::config()->test_publish_key;
            }
        } else {
            if (defined("STRIPE_LIVE_PK")) {
                return STRIPE_LIVE_PK;
            } else {
                return self::config()->live_publish_key;
            }
        }
    }

    /**
     * Either get the publish key from config
     * check if a global constant has been set
     *
     * @return string
     */
    public static function secret_key()
    {
        if(Director::isDev()) {
            if (defined("STRIPE_TEST_SK")) {
                return STRIPE_TEST_SK;
            } else {
                return self::config()->test_secret_key;
            }
        } else {
            if (defined("STRIPE_LIVE_SK")) {
                return STRIPE_LIVE_SK;
            } else {
                return self::config()->live_secret_key;
            }
        }
    }
 }