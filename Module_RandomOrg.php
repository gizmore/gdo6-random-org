<?php
namespace GDO\RandomOrg;

use GDO\Core\GDO_Module;
use GDO\Core\GDT_Secret;
use GDO\Net\HTTP;
use GDO\DB\GDT_UInt;
use GDO\Util\Random;
use GDO\Core\Logger;
use GDO\DB\Database;

/**
 * Simple api for random.org
 * Falls back to gdo6 random generator via openssl.
 * Requests chunks of random numbers and saves them with pack() to a temp file.
 * 
 * @example $number = Module_RandomOrg::instance()->rand(1, 10);
 * 
 * @author gizmore
 * @version 6.10.5
 * @since 6.10.5
 */
final class Module_RandomOrg extends GDO_Module
{
    public function getConfig()
    {
        return [
            GDT_Secret::make('random_org_key')->initial('65b5e4c9-74bc-44c2-a9f2-b32418572f47'),
            GDT_UInt::make('random_org_chunk_size')->initial('1000'),
            GDT_UInt::make('random_org_request_id')->initial('1'),
        ];
    }
    
    public function cfgRandID()
    {
        return $this->getConfigVar('random_org_request_id');
    }
    
    public function cfgKey()
    {
        return $this->getConfigVar('random_org_key');
    }
    
    public function cfgChunkSize()
    {
        return $this->getConfigVar('random_org_chunk_size');
    }
    
    public function rand($min, $max)
    {
        $db = Database::instance();
        $lock = 'RANDOM_ORG';
        try
        {
            $db->lock($lock);
            if (!$this->hasMoreRandomNumbers($min, $max))
            {
                if (!$this->requestMoreRandomNumbers($min, $max))
                {
                    Logger::logError("Cannot request random values!");
                }
            }
            return $this->_rand($min, $max);
        }
        catch (\Throwable $ex)
        {
            Logger::logException($ex);
        }
        finally
        {
            $db->unlock($lock);
        }
        return Random::rand($min, $max);
    }
    
    private function _rand($min, $max)
    {
        $path = $this->randPath($min, $max);
        $fh = fopen($path, 'rw+');
        fseek($fh, -5, SEEK_END);
        $num = fread($fh, 4);
        $n = unpack('i', $num);
        ftruncate($fh, filesize($path)-5);
        fclose($fh);
        return $n[1];
    }
    
    private function randPath($min, $max)
    {
        return $this->tempPath("rand_between_{$min}_and_{$max}.rnd.txt");
    }
    
    /**
     * @param int $min
     * @param int $max
     */
    private function hasMoreRandomNumbers($min, $max)
    {
        $path = $this->randPath($min, $max);
        $size = @filesize($path);
        return $size > 0;
    }
    
    private function requestMoreRandomNumbers($min, $max)
    {
        $url = $this->getRequestURL($min, $max);
        $data = $this->getPostData($min, $max);
        $headers = [
            'Content-Type: application/json',
        ];
        $response = HTTP::post($url, $data, false, $headers);
        $json = json_decode($response, true);
        $data = @$json['result']['random']['data'];
        if ($data)
        {
            return $this->writeMoreRandomNumbers($min, $max, $data);
        }
        return false;
    }

    private function getRequestURL($min, $max)
    {
        return 'https://api.random.org/json-rpc/4/invoke';
    }
    
    private function getPostData($min, $max)
    {
        $data = [
            "jsonrpc" => "2.0",
            "method" => "generateIntegers",
            "params" => [
                "apiKey" => $this->cfgKey(),
                "n" =>  $this->cfgChunkSize(),
                "min" => $min,
                "max" => $max,
                "replacement" => true,
                "base" => 16,
                "pregeneratedRandomization" => null,
            ],
            "id" => $this->cfgRandID(),
        ];
        $this->increaseConfigVar('random_org_request_id');
        return json_encode($data, JSON_PRETTY_PRINT);
    }
    
    private function writeMoreRandomNumbers($min, $max, $data)
    {
        $path = $this->randPath($min, $max);
        $write = '';
        foreach ($data as $number)
        {
            $line = pack('i', hexdec($number));
            $write .= $line . "\n";
        }
        if (!($fh = @fopen($path, 'w+')))
        {
            return false;
        }
        $written = @fwrite($fh, $write);
        @fclose($fh);
        if ($written !== (count($data) * 5))
        {
            return false;
        }
        return true;
    }
    
}
