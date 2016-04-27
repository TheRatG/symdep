<?php
namespace TheRat\SymDep;

/**
 * Class BuildHelper
 *
 * @package TheRat\SymDep
 */
class BuildHelper
{
    const TYPE_DEV = 'dev';
    const TYPE_TEST = 'test';
    const TYPE_PROD = 'prod';
    const TYPE_UNIT_TEST = 'unit_test';

    /**
     * @return string
     */
    public static function getBuildType()
    {
        $options = getopt('::', ['build-type::']);
        $result = self::TYPE_DEV;
        if (array_key_exists('build-type', $options)) {
            $firstLetter = strtolower($options['build-type'])[0];
            $map = [
                'd' => self::TYPE_DEV,
                'u' => self::TYPE_UNIT_TEST,
                't' => self::TYPE_TEST,
                'p' => self::TYPE_PROD,
            ];
            if (array_key_exists($firstLetter, $map)) {
                $result = $map[$firstLetter];
            } else {
                throw new \RuntimeException('Invalid strategy value, must be D | T | P');
            }
        }

        return $result;
    }
}
