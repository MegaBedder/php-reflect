<?php

namespace Bartlett\Reflect\Token;

abstract class TokenWithArgument extends TokenWithScope
{
    protected $arguments;

    public function getArguments()
    {
        if ($this->arguments !== NULL) {
            return $this->arguments;
        }

        $this->arguments = array();
        $i               = $this->id + 1;
        $nextArgument    = array();

        $name = explode('\\', get_class($this));

        if (array_pop($name) === 'FunctionToken') {
            if ($this->tokenStream[$i][0] == 'T_OPEN_BRACKET'
                || $this->tokenStream[$i+1][0] == 'T_OPEN_BRACKET'
            ) {
                // closure
            } else {
                // ampersand before function-name
                if ($this->tokenStream[$i+1][0] == 'T_AMPERSAND') {
                  $i = $i + 3;
                } else {
                  $i = $i + 2;
                }
            }
        }

        while (isset($this->tokenStream[$i])
            && $this->tokenStream[$i][0] != 'T_CLOSE_BRACKET'
        ) {
            if ($this->tokenStream[$i][0] == 'T_WHITESPACE'
                || $this->tokenStream[$i][0] == 'T_OPEN_BRACKET'
            ) {
                // do nothing

            } elseif (in_array($this->tokenStream[$i][0], array('T_STRING', 'T_ARRAY'))
                && !isset($nextArgument['name'])
            ) {
                if (($this->tokenStream[$i+1][0] == 'T_OPEN_BRACKET'
                    || $this->tokenStream[$i+2][0] == 'T_OPEN_BRACKET')
                ) {
                    if ($this->tokenStream[$i][0] == 'T_STRING') {
                        $nextArgument['typeHint'] = 'mixed';
                        $nextArgument['name']     = $this->tokenStream[$i][1];
                    } else {
                        $nextArgument['typeHint'] = '';
                    }

                    // allow for anything inside the brackets
                    while ($this->tokenStream[$i][0] != 'T_CLOSE_BRACKET') {
                        if (!isset($nextArgument['name'])) {
                            $nextArgument['typeHint'] .= $this->tokenStream[$i][1];
                        }
                        $i++;
                    }
                    if (!isset($nextArgument['name'])) {
                        $nextArgument['typeHint'] .= $this->tokenStream[$i][1];
                    }

                } else {
                    $nextArgument['typeHint'] = $this->tokenStream[$i][1];
                }

            } elseif ($this->tokenStream[$i][0] == 'T_AMPERSAND') {
                $nextArgument['byRef'] = TRUE;

            } elseif ($this->tokenStream[$i][0] == 'T_VARIABLE') {
                $nextArgument['name'] = ltrim($this->tokenStream[$i][1], '$');

            } elseif ($this->tokenStream[$i][0] == 'T_EQUAL') {
                // just do nothing - next tokens will contain the defaultValue

            } elseif (
                ($this->tokenStream[$i][0] == 'T_STRING') ||
                ($this->tokenStream[$i][0] == 'T_CONSTANT_ENCAPSED_STRING') ||
                ($this->tokenStream[$i][0] == 'T_LNUMBER')
            ) {
                $nextArgument['defaultValue'] = $this->tokenStream[$i][1];

            } elseif ($this->tokenStream[$i][0] == 'T_ARRAY') {
                $nextArgument['defaultValue'] = $this->tokenStream[$i++][1];

                // allow for anything inside the array, including nested arrays
                $bracketCount = 0;
                while (($this->tokenStream[$i][0] != 'T_CLOSE_BRACKET')
                    || ($bracketCount > 1)
                ) {
                    if ($this->tokenStream[$i][0] == 'T_OPEN_BRACKET') {
                        $bracketCount++;

                    } elseif ($this->tokenStream[$i][0] == 'T_CLOSE_BRACKET') {
                        $bracketCount--;
                    }

                    if (($this->tokenStream[$i][0] == 'T_COMMENT') ||
                        ($this->tokenStream[$i][0] == 'T_DOC_COMMENT')) {
                        // skip comments and doc-comments
                        $i++;
                    } else {
                        $nextArgument['defaultValue'] .= $this->tokenStream[$i++][1];
                    }
                }
                // T_CLOSE_BRACKET
                $nextArgument['defaultValue'] .= $this->tokenStream[$i][1];

            } elseif ($this->tokenStream[$i][0] == 'T_COMMA') {
                if (isset($nextArgument['typeHint'])
                    && !isset($nextArgument['name'])
                ) {
                    $nextArgument['defaultValue'] = $nextArgument['typeHint'];
                    unset($nextArgument['typeHint']);
                    if ('stdClass' == $nextArgument['defaultValue']) {
                        $nextArgument['typeHint'] = 'object';
                    }
                }
                $nextArgument['position'] = count($this->arguments);

                // flush argument to array
                $this->arguments[] = $nextArgument;
                $nextArgument      = array();
            }

            $i++;
        }
        if (!empty($nextArgument)) {
            if (isset($nextArgument['typeHint'])
                && !isset($nextArgument['name'])
            ) {
                $nextArgument['defaultValue'] = $nextArgument['typeHint'];
                unset($nextArgument['typeHint']);
                if ('stdClass' == $nextArgument['defaultValue']) {
                    $nextArgument['typeHint'] = 'object';
                }
            }
            $nextArgument['position'] = count($this->arguments);
            $this->arguments[] = $nextArgument;
        }

        return $this->arguments;
    }
}