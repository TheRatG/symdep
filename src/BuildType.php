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
        $options = $this->parseParameters();
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

    /**
     * Parses $GLOBALS['argv'] for parameters and assigns them to an array.
     *
     * Supports:
     * -e
     * -e <value>
     * --long-param
     * --long-param=<value>
     * --long-param <value>
     * <value>
     *
     * @param array $noopt List of parameters without values
     * @return array
     */
    protected function parseParameters($noOpt = [])
    {
        $result = [];
        $params = $_SERVER['argv'];
        reset($params);
        while ($p = current($params)) {
            next($params);
            if ($p{0} == '-') {
                $pName = substr($p, 1);
                $value = true;
                if ($pName{0} == '-') {
                    // long-opt (--<param>)
                    $pName = substr($pName, 1);
                    if (strpos($p, '=') !== false) {
                        // value specified inline (--<param>=<value>)
                        list($pName, $value) = explode('=', substr($p, 2), 2);
                    }
                }
                // check if next parameter is a descriptor or a value
                $nextParam = current($params);
                if (!in_array($pName, $noOpt) && $value === true && $nextParam !== false && $nextParam{0} != '-') {
                    $value = current($params);
                    next($params);
                }
                $result[$pName] = $value;
            } else {
                // param doesn't belong to any option
                $result[] = $p;
            }
        }
        return $result;
    }
}
