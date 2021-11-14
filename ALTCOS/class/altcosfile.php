 <?php
require_once "repo.php";
class altcosfile {

    function __construct($ref) {
      $file = $_SERVER['DOCUMENT_ROOT'] . "/ALTCOS/streams/" . repos::refToDir($ref) . "/ALTCOSfile";
//       $this->operators = [];
      if (!file_exists($file)) {
        $this->error = "Файл $file отсутствует";
        return;
      }
      $this->ref = $ref;
      $fp = fopen($file, 'r');
      $this->data = json_decode(fread($fp, filesize($file)), true);
//       $nstr = 0;
//       while (strlen(trim($str = fgets($fp))) == 0 || substr($str,0,1) == '#') $nstr+=1;
//       if (rtrim(substr($str, 0, 5)) != 'FROM') {
//         $this->error = "Строка $nstr. Первый оператор отличается от 'FROM'";
//         return;
//       }
//       $path = explode(' ', $str);
//       $this->from = trim($path[1]);
//
//       $operator = false;
//       while ($str = fgets($fp)) {
//         $nstr += 1;
//         if (substr($str,0,1) == '#') continue;
//         $str = trim($str);
//         if (strlen($str) > 0) {
//           if (!$operator) {
//             $path = explode(' ', $str, 2);
//             $operator = $path[0];
//             $operatorContent = [ $path[1] ];
//             if (substr($str, -1) != '\\') {
//               $this->operators[] = [ $operator => $operatorContent ];
//               $operator = false;
//             }
//           } else {
//             $operatorContent[] = $str;
//             if (substr($str, -1) != '\\') {
//               $this->operators[] = [ $operator => $operatorContent ];
//               $operator = false;
//             }
//           }
//         } else {
//           if ($operator) {
//             $this->warning = "Строка $nstr. Не окончен оператор $operator";
//             $this->operators[] = [ $operator => $operatorContent ];
//             $operator = false;
//           }
//         }
//       }
//       if ($operator) {
//         $this->warning = "Строка $nstrl Не окончен оператор $operator";
//         $this->operators[] = [ $operator => $operatorContent ];
//         $operator = false;
//       }
    }

    static function getAcosSubRefs($ref) {
      $refDir = $_SERVER['DOCUMENT_ROOT'] . "/ALTCOS/streams/" . repos::refToDir($ref);
      $ret = [];
      $fd = dir($refDir);
      while ($entry = $fd->read()) {
        if (substr($entry, 0, 1) == '.' || in_array($entry, ['vars', 'roots'])) continue;
        $acosDir = "$refDir/$entry";
        $acosFile = "$acosDir/ALTCOSfile";
        if (file_exists($acosFile)) {
          $subRef = repos::dirToRef("$ref/$entry");
          $ret[] = $subRef;
        }
      }
      return $ret;
    }

}
