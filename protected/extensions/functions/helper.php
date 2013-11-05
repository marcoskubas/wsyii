<?php
 /**
     * Imprime valores dependendo do formato e não morre
     */
    function printr($val) {

        if ( is_object($val) || is_array($val) )
            print_r($val);
        else if ( empty($val) || is_resource($val) )
            var_dump($val);
        else
            echo $val;
    }