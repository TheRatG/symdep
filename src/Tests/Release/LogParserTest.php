<?php
namespace TheRat\SymDep\Tests\Release;

use TheRat\SymDep\Release\LogParser;

class LogParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param $log
     * @param $expected
     * @dataProvider dataProviderTestExecute
     */
    public function testExecute($log, $expected)
    {
        $parser = new LogParser();
        $actual = $parser->execute($log);

        $this->assertEquals($expected, $actual);
    }

    public function dataProviderTestExecute()
    {
        $data = [];

        $data[] = [
            [
                '6ecde748cdcaa6502583e94744b00de73ab65f89 OPTION-1894',
                '153f94996e599a4569b07482874906f0ab79a5fb OPTION-1869: fix unsubscribe url',
            ],
            [
                'OPTION-1894',
                'OPTION-1869',
            ],
        ];

        $data[] = [
            [
                '6ecde748cdcaa6502583e94744b00de73ab65f89 OPTION-1894 OPTION-1869 DEV-193',
            ],
            [
                'OPTION-1894',
                'OPTION-1869',
                'DEV-193',
            ],
        ];

        $data[] = [
            [
                '6ecde748cdcaa6502583e94744b00de73ab65f89 OPTION-1894',
                '153f94996e599a4569b07482874906f0ab79a5fb OPTION-1869: fix unsubscribe url',
                'b94af4334f4b847bbbb34586a2e2cfb639ee20fa OPTION-1222: yandex limit',
                '924443a2feaf5600680576ac811adf02add4490a Auto merge accept',
                '7f6b64253096abe537250f510bcb874167072220 OPTION-1537: link translatable\\',
                '43331743feb51b7f9bf98877e2a09b4f39483195 Auto merge accept',
                'd25283ed14f631a53ac075a65df6d9590072339a Merge branch \'OPTION-1720\' of git.srv.robofx.com:robooption/lk',
                '634c1f26c25415c2469e32817a153210654e496e update symdep',
                '6eec41ae9a29791d03979982f920dc8048bfdd9c OPTION-1868: zip validation',
                '9a90d18a0aa08be7211370cea7dcd5813387570d Merge branch \'master\' of git.srv.robofx.com:robooption/lk into OPTION-1868',
                '19c7299570abf4c96f114726e9d5d509a251823d OPTION-1862: multiple choice',
                'f62679df6542db30087eb320a6d00f7abede4b63 added payment system name to log',
                'dfc0d8be21fbf656686b937faa858666039d962a OPTION-1720: deprecated',
                '28d6d42c9f5e690b475922afb49f9faa1f06b653 OPTION-1863: fix import',
                '7ff143d2ced8779b21a58fdad7ffdae10588719c OPTION-1814: all platform ids',
                '14402cbfd2d656b4717468607ce4531e957d5740 turn on platform.socket.js',
                '13e6a91b2657b4be67c69a6221d4896a997655d8 turn of platform.socket.js',
                '2c19c1eb51b6897ebee31945a1da3ad6743a150a update config',
                '0a509d924c09cf1f4fa9b7c6a73e844631212457 Merge branch \'master\' of git.srv.robofx.com:robooption/lk',
                '88ce99e8a98625303db0a83880f998d49ee2a4df Merge branch \'OPTION-1863\' of git.srv.robofx.com:robooption/lk',
                '8e38fb95d324691e08cfe4e67e88d99fa24e938f Auto merge accept',
                '481c3852d44864f199937f2a34bfafdf6b98c061 OPTION-1868: zip for some verifications',
                '7824875c58853a6d9a67e6374cf08b52db4c8d6e OPTION-1863: remove parameter',
                '45672622f958b5e30d53975b977cf9f173250627 OPTION-1863: webinar names patterns',
                'e97d8b456f29796109de2c1470ffb2a9605720bd OPTION-1863: webinar names patterns',
            ],
            [
                'OPTION-1894',
                'OPTION-1869',
                'OPTION-1222',
                'OPTION-1537',
                'OPTION-1720',
                'OPTION-1868',
                'OPTION-1862',
                'OPTION-1863',
                'OPTION-1814',
            ],
        ];

        return $data;
    }
}
