<?php
namespace GDO\RandomOrg\Test;

use GDO\Tests\TestCase;
use GDO\RandomOrg\Module_RandomOrg;
use function PHPUnit\Framework\assertLessThanOrEqual;
use function PHPUnit\Framework\assertGreaterThanOrEqual;

final class RandomTest extends TestCase
{
    public function testAPI()
    {
        $mod = Module_RandomOrg::instance();
        $n = $mod->cfgChunkSize() + 10; # test to exceed chunk size.
        $sum = 0;

        for ($i = 0; $i < $n; $i++)
        {
            $rand = $mod->rand(1, 10);
            if (!$rand)
            {
                echo 1;
            }
            $sum += $rand;
            assertLessThanOrEqual(10, $rand, 'TestMax if random number is in range');
            assertGreaterThanOrEqual(1, $rand, 'TestMin if random number is in range');
        }
        
        $sum /= $n;
        assertLessThanOrEqual(6.0, $sum, 'TestMax if random number stochastic matches.');
        assertGreaterThanOrEqual(5.0, $sum, 'TestMin if random number stochastic matches.');
    }
    
}
