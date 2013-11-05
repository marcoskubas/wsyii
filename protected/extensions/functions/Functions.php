<?php
    class Functions {
        /**
         * Imprime valores dependendo do formato e não morre
         */
        public static function printr($val) {

            CVarDumper::dump($val, 10, true);
            // echo "<pre>";
            // if ( is_object($val) || is_array($val) )
            //     print_r($val);
            // else if ( empty($val) || is_resource($val) )
            //     var_dump($val);
            // else
            //     echo $val;
            // echo "</pre>";
        }

        /**
         * Retorna decode JSON in Array
         * @example in ERestHelperScopes sortBy
         */
        public static function JSONdecode($json){
            
            $format = array();
            $result = array();
            $decode = str_replace(array('[',']',' '), "", $json);
            $chaves = explode("{", $json);
            $positions = explode("},{", $decode);

            if(count($positions) == 1 && count($chaves) == 1){
                $positions = explode(",", $decode);
                foreach ($positions as $position) {
                    $decode = str_replace(array('{','}','\'','"'), "", $position);
                    $result[] = $decode;
                }
            }else{
                foreach ($positions as $position) {
                    $decode = str_replace(array('{','}','\'','"'), "", $position);
                    $decode = explode(",", $decode);
                    $part1 = explode(":", $decode[0]); //property
                    $part2 = explode(":", $decode[1]); //direction
                    $format[$part1[0]] = $part1[1];
                    $format[$part2[0]] = $part2[1];
                    $result[] = $format;
                }
            }
            
            return $result;
        }
    }
