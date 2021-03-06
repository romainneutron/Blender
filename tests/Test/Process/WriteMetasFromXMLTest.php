<?php

namespace Test\Process;

use Blender\Process;
use Blender\Config;
use Blender\Database;
use Doctrine\DBAL\Configuration;
use Monolog\Logger;
use Blender\OutputHandler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\CssSelector\CssSelector;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class WriteMetasFromXMLTest extends \PHPUnit_Framework_TestCase
{

  public static $filesystem;
  protected $process;

  public static function setupBeforeClass()
  {
    self::$filesystem = new Filesystem();
  }

  protected function setUp()
  {
    $output = new ConsoleOutput();

    $logger = new Logger('WriteMetasFromXML');
    $logger->pushHandler(new OutputHandler($output));

    $options = array(
        'no_backup' => false
        , 'allow_duplicate' => false
    );

    self::$filesystem->remove(__DIR__ . '/../../ressources/blender.sqlite');
    self::$filesystem->remove(glob(__DIR__ . "/../../ressources/output/*.jpg"));
    self::$filesystem->remove(__DIR__ . '/../../ressources/tmp');

    $database = new Database(
                    array(
                        'path' => __DIR__ . '/../../ressources/blender.sqlite',
                        'driver' => 'pdo_sqlite'
                    ),
                    new Configuration()
    );

    $config = new Config(__DIR__ . '/../../ressources/jir.config.yml');

    $process = new Process\WriteMetasFromXML(
                    $config
                    , $database
                    , $logger
                    , new ParameterBag($options)
    );

    $tmpPath = __DIR__ . '/../../ressources/tmp';

    $process->setTempFolder($tmpPath . '/copy');
    $process->setLogFolder($tmpPath . '/log');
    $process->setBackupFolder($tmpPath . '/backup');

    $this->process = $process;
  }

  protected function tearDown()
  {
    self::$filesystem->remove(__DIR__ . '/../../ressources/blender.sqlite');
    self::$filesystem->remove(glob(__DIR__ . "/../../ressources/output/*.jpg"));
    self::$filesystem->remove(__DIR__ . '/../../ressources/tmp');
  }

  public function testBlender()
  {
    $inputDir = __DIR__ . '/../../ressources/input';
    $outputDir = __DIR__ . '/../../ressources/output';

    $this->process->blend($inputDir, $outputDir);

    $exiftoolBinary = __DIR__ . '/../../../vendor/alchemy/exiftool/exiftool';

    $metas = array(
        'NomdelaPhoto' => array(
            'src' => 'IPTC:Headline',
            'value' => 'hello'),
        'Rubrique' => array(
            'src' => 'IPTC:Category',
            'value' => 'salut'),
// @deprecated field
//        'SousRubrique' => array(
//            'src' => 'IPTC:SupplementalCategories',
//            'value' => 'bye'),
        'MotsCles' => array(
            'src' => 'IPTC:Keywords',
            'value' => 'kakoo'),
        'DatedeParution' => array(
            'src' => 'IPTC:Source',
            'value' => '2012/04/13'),
        'DatePrisedeVue' => array(
            'src' => 'IPTC:DateCreated',
            'value' => '2012:04:13'),
        'Ville' => array(
            'src' => 'IPTC:City',
            'value' => 'paris'),
        'Pays' => array(
            'src' => 'IPTC:Country-PrimaryLocationName',
            'value' => 'france'),
        'Copyright' => array(
            'src' => 'IPTC:CopyrightNotice',
            'value' => 'yata')
    );

    $cmd = $exiftoolBinary . ' -X ' . __DIR__ . '/../../ressources/output/1.jpg';
    $output = shell_exec($cmd);
    if ($output)
    {
      $document = new \DOMDocument();
      $document->loadXML($output);
      $xpath = new \DOMXPath($document);

      $xPathQuery = CssSelector::toXPath('*');
      foreach ($metas as $metaInfo)
      {
        $found = false;
        foreach ($xpath->query($xPathQuery) as $node)
        {
          $nodeName = $node->nodeName;
          $value = $node->nodeValue;
          if ($nodeName == $metaInfo['src'])
          {
            $this->assertEquals($value, $metaInfo['value']);
            $found = true;
            continue;
          }
        }
        if ( ! $found)
        {
          $this->fail('missing ' . $metaInfo['src']);
        }
      }
    }
  }

}
