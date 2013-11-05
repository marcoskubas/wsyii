<?php

// uncomment the following to define a path alias
// Yii::setPathOfAlias('local','path/to/local-folder');
// This is the main Web application configuration. Any writable
// CWebApplication properties can be configured here.
return array(
    'basePath' => dirname(__FILE__) . DIRECTORY_SEPARATOR . '..',
    'name' => 'WS Plataforma',
    'language' => 'pt',
    // preloading 'log' component
    'preload' => array( 'log' ),
    // autoloading model and component classes
    'import' => array(
        'application.models.*',
        'application.components.*',
        'ext.restfullyii.components.*',
        'ext.functions.*',
    ),
    'modules' => array(
        // uncomment the following to enable the Gii tool

        'gii' => array(
            'class' => 'system.gii.GiiModule',
            'password' => 'root',
            // If removed, Gii defaults to localhost only. Edit carefully to taste.
            'ipFilters' => array( '127.0.0.1', '::1' ),
        ),
    ),
    // application components
    'components' => array(
        'cache' => array(
            'class' => 'CFileCache'
        ),
        'user' => array(
            // enable cookie-based authentication
            'allowAutoLogin' => true,
            'autoRenewCookie' => true,
        ),
        'session' => array(
            'autoStart' => true,
            'class' => 'system.web.CDbHttpSession',
            'connectionID' => 'db',
            'sessionTableName' => 've_sessions',
        ),
        'urlManager' => array(
            'urlFormat' => 'path',
            'showScriptName' => true,
            'caseSensitive' => false,
            'rules' => require(dirname(__FILE__) . '/../extensions/restfullyii/config/routes.php'),
        ),
        'db' => require(dirname(__FILE__) . '/../data/config_db.php'),
        'errorHandler' => array(
            // use 'site/error' action to display errors
            'errorAction' => 'site/error',
        ),
        'log' => array(
            'class' => 'CLogRouter',
            'routes' => array(
                array(
                    'class' => 'CFileLogRoute',
                    'levels' => 'profile,warning, error, info',
                    // 'levels'=>'profile', //warning, error, info
                    'categories' => 'system.db.*',
                    'logFile' => 'sql.log'
                ),
            /*
              array(
              'class' => 'CWebLogRoute',
              'levels' => 'profile'
              )
             */
            ),
        ),
    ),
    // application-level parameters that can be accessed
    // using Yii::app()->params['paramName']
    'params' => array(
        // this is used in contact page
        'adminEmail' => 'webmaster@example.com',
        'RESTusername' => 'admin@restuser',
        'RESTpassword' => 'admin@Access',
    ),
);