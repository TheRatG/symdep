<?php
namespace TheRat\SymDep;

/**
 * Class BuildType
 *
 * @package TheRat\SymDep
 */
class BuildType
{
    const TYPE_DEV = 'dev';
    const TYPE_TEST = 'test';
    const TYPE_PROD = 'prod';

    /**
     * @return string
     */
    public function getType()
    {
        $options = getopt('::', ['build-type::']);
        $result = self::TYPE_DEV;
        if (array_key_exists('build-type', $options)) {
            $firstLetter = strtolower($options['build-type'])[0];
            $map = [
                'd' => self::TYPE_DEV,
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

    /**
     * @param $type
     * @return string
     */
    public function getRecipeFile($type)
    {
        $map = [
            self::TYPE_DEV => 'local.php',
            self::TYPE_TEST => 'test.php',
            self::TYPE_PROD => 'prod.php',
        ];

        return $map[$type];
    }
}
