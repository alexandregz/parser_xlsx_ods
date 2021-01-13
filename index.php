<?php
/**
 * - install spout: composer require box/spout
 * 
 * Uso: alex@vosjod:~/Development/spout(master)$ php index.php |more
............. 
[...]
xxxxx@xxxxx.com:
        dia: xx/xx/2020 -       loginAnterior: xx/xx/2020 06:53:08 - logout: xx/xx/2020 15:01:43         - diff: 08:08:35
        dia: xx/xx/2020 -       loginAnterior: xx/xx/2020 06:51:35 - logout: xx/xx/2020 15:02:03         - diff: 08:10:28
        dia: xx/xx/2020 -       loginAnterior: xx/xx/2020 06:56:05 - logout: xx/xx/2020 15:00:07         - diff: 08:04:02
[...]
xxxxx@xxxxx.com:
        dia: xx/xx/2020 -       loginAnterior: xx/xx/2020 14:55:xx - logout: xx/xx/2020 23:01:33         - diff: 08:06:xx
        dia: xx/xx/2020 -       loginAnterior: xx/xx/2020 14:55:32 - logout: xx/xx/2020 23:02:51         - diff: 08:07:19
[...]
xxxxx@xxxxx.com:
	dia: xx/xx/2020 - 	loginAnterior: xx/xx/2020 06:35:34 - logout: NOM FIXO	 - diff:
	dia: xx/xx/2020 - 	loginAnterior: xx/xx/2020 07:34:57 - logout: xx/xx/2020 09:52:12	 - diff: 02:xx:15
	dia: xx/xx/2020 - 	loginAnterior: xx/xx/2020 07:02:29 - logout: NOM FIXO	 - diff:
    dia: xx/xx/2020 - 	loginAnterior: xx/xx/2020 07:40:50 - logout: NOM FIXO	 - diff:
[...]
 */

require_once 'vendor/autoload.php';

@$ficheiro = $argv[1];
if(!$ficheiro) die("Uso: php index.php ficheiro\n");

$formato = 'd/m/Y H:i:s';



use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;

$reader = ReaderEntityFactory::createReaderFromFile($ficheiro);
$reader->open($ficheiro);



$registosPorUsuario = array();

// 1) agrupo por usuario
foreach ($reader->getSheetIterator() as $sheet) {

    $contador = 0;
    foreach ($sheet->getRowIterator() as $row) {
        echo ".";

        $cells = $row->getCells();
        // var_export($cells);die();  
        // echo $cells[1]."--------\n";


        $values = array_map(function($cell) {
            return $cell->getValue();
        }, $row->getCells());

        if($contador > 0) {
            $registosPorUsuario["{$cells[1]}"][] = $values;
        }
        // foreach($cells as $k => $cell) {
        //     echo "$k: ".$cells[$k]->getValue()."\n";
        // }

        $contador++;
	}
}
$reader->close();

echo "\n";

// 2) ordeo por data, de menor a maior (para xuntar login e logout do dia)
$loginAnterior = null;
foreach($registosPorUsuario as $user => $datos) {
    asort($datos);
    $loginAnterior = null;
    // var_export($datos);die();

    echo $user." ({$datos[0][2]}):\n";
    // var_export($datos); continue;
    
    // 3) comparo login e logout
    foreach($datos as $v) {
        if($v[4] == 'login') {

            // login sem haber feito logout!, fago output e reasigno ao novo login
            if($loginAnterior != null) {
                @list($dia, $hora) = explode(' ', $loginAnterior);

                // no ficheiro Fichaxes_14102020.xlsx hai valores incorrectos nos tempos nalgúns casos, pero revissando manualmente
                // nom existem. Quizais polo parseo do xlsx co spout? escapo porque os resultados som correctos sem esses datos, assi que comprobo dia                
                preg_match('/\d{2}\/\d{2}\/\d{4}/', $dia, $matches);
                if(count($matches) > 0) {
                    echo "\tdia: $dia - ";
                    echo "\tloginAnterior: $loginAnterior - logout: NOM FIXO";
                    echo "\t - diff: \n";
                }
            }

            // para .xlsx os campos de Fecha aparecem automaticamente como DateTime, polo que convirtoos a string
            // if(substr($ficheiro, -5) == '.xlsx') {
            if(gettype($v[6]) == 'object') {
                $result = $v[6]->format($formato);

                if($result) {
                    $loginAnterior = $result;
                }
                else {
                    die("Pete de format!");
                }
            }
            else {
                $loginAnterior = $v[6];
            }
            continue;
        }

        if($v[4] == 'logout' && $loginAnterior != null) {
            $horaLogout = $v[6];
            if(gettype($horaLogout) == 'object') {
                $horaLogout = $v[6]->format($formato);
            }
            
            $dia = '';
            $hora = '';
            $diferenciaLoginLogout = 'INCORRECTO';

            // no ficheiro Fichaxes_14102020.xlsx hai valores incorrectos nos tempos nalgúns casos, pero revissando manualmente
            // nom existem. Quizais polo parseo do xlsx co spout? escapo porque os resultados som correctos sem esses datos.
            if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4}) (\d{2})\:/', $loginAnterior) || !preg_match('/^(\d{2})\/(\d{2})\/(\d{4}) (\d{2})\:/', $horaLogout)) {
                // echo "\t\tloginAnterior: [$loginAnterior] - horaLogout: [$loginAnterior]\n";
                // var_export($v);

                // escapo output
                continue;
            }
            else {
                list($dia, $hora) = explode(' ', $loginAnterior);
                $diferenciaLoginLogout = diferenciaTempoLoginELogout($loginAnterior, $horaLogout);
            }

            echo "\tdia: $dia - ";
            echo "\tloginAnterior: $loginAnterior - logout: ".$horaLogout;
            echo "\t - diff: ".$diferenciaLoginLogout."\n";            
        }
    
        $loginAnterior = null;        
    }

    // ultimo login (sem logout) que nom se amossa porque pasaria de usuario
    if($loginAnterior != null) {        
        @list($dia, $hora) = explode(' ', $loginAnterior);
        if(preg_match('/^(\d{2}\/\d{2}\/\d{4})$/', $dia) === false) {
            continue;
        }        
        echo "\tdia: $dia - ";
        echo "\tloginAnterior: $loginAnterior - logout: NOM FIXO";
        echo "\t - diff: \n";
    }    
}



// compara duas datas, dando a diferencia en horas, minutos e segundos (string)
function diferenciaTempoLoginELogout($login, $logout) {
    global $formato;

    $datetime1 = DateTime::createFromFormat($formato, $login);
    $datetime2 = DateTime::createFromFormat($formato, $logout);
    
    $interval = $datetime1->diff($datetime2);
    return $interval->format('%R%a %H:%I:%S');
}
