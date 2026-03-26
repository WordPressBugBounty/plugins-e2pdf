<?php

/**
 * File: /helper/e2pdf-math.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

/** @license
 * Based on https://gist.github.com/ircmaxell/1232629
 */
class Helper_E2pdf_Math {

    private $helper;
    protected $variables = [];
    protected $stack = [];
    protected $output = [];

    public function __construct() {
        $this->helper = Helper_E2pdf_Helper::instance();
    }

    public function pre_render($value = '', $extension = null) {
        $replacements = [];
        if ($value) {
            preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $value, $matches);
            $tagnames = $matches[1];
            if (!empty($tagnames)) {
                preg_match_all('/' . $this->helper->load('shortcode')->get_shortcode_regex($tagnames) . '/', $value, $shortcodes);
                foreach ($shortcodes[0] as $key => $shortcode_value) {
                    $index = count($replacements) + 1;
                    $replacements[] = $shortcode_value;
                    $value = str_replace($shortcode_value, '%' . $index . '$s', $value);
                }
            }

            if (method_exists($extension, 'strip_math')) {
                $value = $extension->strip_math($value, $replacements);
            }

            if (!empty($replacements)) {
                $value = preg_replace('/%(?!\d+\$s)/', '%%', $value);
                $value = vsprintf(
                        $value, array_map(
                                function ($v) {
                                    return '(' . $v . ')';
                                }, $replacements
                        )
                );
            }
        }
        return $value;
    }

    public function after_render($value) {
        return str_replace('()', '0', $value);
    }

    public function evaluate($string) {
        try {
            $this->stack = $this->parse($string);
            return $this->run();
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    public function parse($string) {
        $tokens = $this->tokenize(str_replace(' ', '', $string));
        $this->output = [];
        $this->operators = [];
        foreach ($tokens as $token) {
            $token = $this->extractVariables($token);
            $expression = $this->factory($token);
            if ($expression->operator) {
                $this->parseOperator($expression);
            } elseif (isset($expression->type) && $expression->type == 'parenthesis') {
                $this->parseParenthesis($expression);
            } else {
                $this->output[] = $expression;
            }
        }
        // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
        while (($op = array_pop($this->operators))) {
            if (isset($op->type) && $op->type == 'parenthesis') {
                throw new Exception(__('Mismatched Parenthesis in Math operation', 'e2pdf'));
            }
            $this->output[] = $op;
        }
        return $this->output;
    }

    public function registerVariable($name, $value) {
        $this->variables[$name] = $value;
    }

    public function run() {
        // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
        while (($operator = array_pop($this->stack)) && $operator->operator) {
            $value = $this->operate($operator);
            if (!is_null($value)) {
                $this->stack[] = $this->factory($value);
            }
        }
        return $operator ? $operator->value : $this->render();
    }

    protected function extractVariables($token) {
        if ($token[0] == '$') {
            $key = substr($token, 1);
            return isset($this->variables[$key]) ? $this->variables[$key] : 0;
        }
        return $token;
    }

    protected function render() {
        $output = '';
        // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
        while (($el = array_pop($this->stack))) {
            $output .= $el->value;
        }
        if ($output) {
            return $output;
        }
        throw new Exception(__('Could not parse Math operation', 'e2pdf'));
    }

    protected function parseParenthesis($expression) {
        if ($expression->open) {
            $this->operators[] = $expression;
        } else {
            $clean = false;
            // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
            while (($end = array_pop($this->operators))) {
                if (isset($end->type) && $end->type == 'parenthesis') {
                    $clean = true;
                    break;
                } else {
                    $this->output[] = $end;
                }
            }
            if (!$clean) {
                throw new Exception(__('Mismatched Parenthesis in Math operation', 'e2pdf'));
            }
        }
    }

    protected function parseOperator($expression) {
        $end = end($this->operators);
        if (!$end) {
            $this->operators[] = $expression;
        } elseif ($end->operator) {
            do {
                if ($expression->operator && $expression->precidence <= $end->precidence) {
                    $this->output[] = array_pop($this->operators);
                } elseif (!$expression->operator && $expression->precidence < $end->precidence) {
                    $this->output[] = array_pop($this->operators);
                } else {
                    break;
                }
            } while (($end = end($this->operators)) && $end->operator); // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition

            $this->operators[] = $expression;
        } else {
            $this->operators[] = $expression;
        }
    }

    protected function tokenize($string) {
        $parts = preg_split('((\b\d[\d.]*\b|\+|-|\(|\)|\*|\^|¦|/)|\s+)', $string, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $parts = array_map('trim', $parts);

        $tokens = [];
        $expectOperand = true;

        $count = count($parts);
        for ($i = 0; $i < $count; $i++) {
            $token = $parts[$i];
            if (($token === '+' || $token === '-') && $expectOperand) {
                $sign = 1;
                while ($i < $count && ($parts[$i] === '+' || $parts[$i] === '-')) {
                    if ($parts[$i] === '-') {
                        $sign *= -1;
                    }
                    $i++;
                }
                if ($i >= $count || $parts[$i] === ')') {
                    if (!empty($tokens)) {
                        $last = end($tokens);
                        if (in_array($last, ['+', '-', '*', '/', '^', '¦'], true)) {
                            array_pop($tokens);
                        }
                    }
                    if ($i < $count) {
                        $i--;
                    }
                    $expectOperand = false;
                    continue;
                }
                $next = $parts[$i];
                if (is_numeric($next)) {
                    $tokens[] = ($sign === -1) ? (string) (-1 * floatval($next)) : $next;
                    $expectOperand = false;
                } elseif ($next === '(') {
                    if ($sign === -1) {
                        $tokens[] = '-1';
                        $tokens[] = '*';
                    }
                    $tokens[] = '(';
                    $expectOperand = true;
                } else {
                    $tokens[] = $next;
                    $expectOperand = false;
                }
            } elseif ($token === '(') {
                $tokens[] = $token;
                $expectOperand = true;
            } elseif ($token === ')') {
                $tokens[] = $token;
                $expectOperand = false;
            } elseif (in_array($token, ['+', '-', '*', '/', '^', '¦'], true)) {
                $tokens[] = $token;
                $expectOperand = true;
            } else {
                $tokens[] = $token;
                $expectOperand = false;
            }
        }
        return $tokens;
    }

    protected function factory($value) {
        $expression = new stdClass();
        $expression->open = false;
        $expression->operator = false;
        $expression->precidence = 0;
        $expression->type = 'expression';
        if (is_object($value)) {
            return $value;
        } elseif (is_numeric($value)) {
            $expression->type = 'number';
        } elseif ($value == '+') {
            $expression->operator = true;
            $expression->type = 'addition';
            $expression->precidence = 4;
        } elseif ($value == '-') {
            $expression->operator = true;
            $expression->type = 'subtraction';
            $expression->precidence = 4;
        } elseif ($value == '*') {
            $expression->operator = true;
            $expression->type = 'multiplication';
            $expression->precidence = 5;
        } elseif ($value === '¦') {
            $expression->operator = true;
            $expression->type = 'modulus';
            $expression->precidence = 5;
        } elseif ($value == '/') {
            $expression->operator = true;
            $expression->type = 'division';
            $expression->precidence = 5;
        } elseif ($value == '^') {
            $expression->operator = true;
            $expression->type = 'power';
            $expression->precidence = 6;
        } elseif (in_array($value, ['(', ')'], true)) {
            $expression->type = 'parenthesis';
            $expression->precidence = 7;
            $expression->open = $value == '(';
        } else {
            $value = 0;
            $expression->type = 'number';
        }
        $expression->value = $value;
        return $expression;
    }

    protected function operate($expression) {
        $value = null;
        if (isset($expression->type)) {
            switch ($expression->type) {
                case 'number':
                    $value = $expression->value;
                    break;
                case 'addition':
                    $value = $this->operate(array_pop($this->stack)) + $this->operate(array_pop($this->stack));
                    break;
                case 'subtraction':
                    $left = $this->operate(array_pop($this->stack));
                    $right = $this->operate(array_pop($this->stack));
                    $value = $right - $left;
                    break;
                case 'multiplication':
                    $value = $this->operate(array_pop($this->stack)) * $this->operate(array_pop($this->stack));
                    break;
                case 'division':
                    $left = $this->operate(array_pop($this->stack));
                    $right = $this->operate(array_pop($this->stack));
                    $value = ($left == 0) ? 0 : $right / $left;
                    break;
                case 'power':
                    $left = $this->operate(array_pop($this->stack));
                    $right = $this->operate(array_pop($this->stack));
                    $value = pow($right, $left);
                    break;
                case 'modulus':
                    $left = $this->operate(array_pop($this->stack));
                    $right = $this->operate(array_pop($this->stack));
                    $value = ($left == 0) ? 0 : ($right % $left);
                    break;
                default:
                    break;
            }
        }
        return $value;
    }
}
