<?php

namespace Bolt;

class Html extends Component
{

    private mixed $_model;
    private bool $_useBootstrap = true;

    public function __construct(mixed $model)
    {
        $this->setModel($model);
    }

    public function setModel(mixed $model): Html
    {
        $this->_model = is_array($model) ? (object) $model : $model;
        return $this;
    }

    public function useBootstrap(bool $bool): Html
    {
        $this->_useBootstrap = $bool;
        return $this;
    }

    private function makeAttributes(array $attrs): string
    {
        if ($this->_useBootstrap) {
            $attrs['class'] = isset($attrs['class']) ? $attrs['class'] . ' form-control' : 'form-control';
        }
        $attributes = [];
        foreach ($attrs as $key => $val) {
            $attributes[] = $key . '="' . htmlspecialchars($val) . '"';
        }
        return empty($attributes) ? '' : implode(' ', $attributes);
    }

    private function createLabel(string $name): string
    {
        return ucwords(str_replace('_', ' ', Helper::camelToUnderScore(trim(str_replace(['[]', '[', ']'], ' ', $name)))));
    }

    private function getValue(string $name): mixed
    {
        if (property_exists($this->_model, $name)) {
            return $this->_model->$name;
        }
        return isset($this->_model[$name]) ? $this->_model[$name] : '';
    }

    private function matchOption(mixed $val1, mixed $val2)
    {
        return $val1 == $val2 ? ' selected="selected"' : '';
    }

    private function createOptions(mixed $options, string $label, string $value)
    {
        $result = $label ? '<option value="">' . $label . '</option>' : '';
        if (is_array($options)) {
            if (isset($options[0]) && (is_array($options[0]) || is_object($options[0])) && count($options) == 3 && is_string($options[1]) && is_string($options[2])) {
                if (is_array($options[0]) || is_a($options[0], 'ArrayAccess')) {
                    $val = $options[1];
                    $text = $options[2];
                    foreach ($options[0] as $option) {
                        $result .= '<option' . $this->matchOption(is_array($option) ? $option[$val] : $option->$val, $value) . ' value="' . htmlspecialchars(is_array($option) ? $option[$val] : $option->$val) . '">' . (is_array($option) ? $option[$text] : $option->$text) . '</option>';
                    }
                }
            } else {
                foreach ($options as $key => $option) {
                    if (is_string($option)) {
                        $result .= '<option' . $this->matchOption(isset($options[count($options) - 1]) ? $option : $key, $value) . ' value="' . htmlspecialchars(isset($options[count($options) - 1]) ? $option : $key) . '">' . $option . '</option>';
                    } else if (isset($option[0], $option[1])) {
                        $result .= '<option' . $this->matchOption($option[0], $value) . ' value="' . htmlspecialchars($option[0]) . '">' . $option[1] . '</option>';
                    } else if (isset($option['value'], $option['text'])) {
                        $result .= '<option' . $this->matchOption($option['value'], $value) . ' value="' . htmlspecialchars($option['value']) . '">' . $option['text'] . '</option>';
                    } else if (isset($option[0])) {
                        $result .= '<option' . $this->matchOption($option[0], $value) . ' value="' . htmlspecialchars($option[0]) . '">' . $option[0] . '</option>';
                    }
                }
            }
        }
        return $result;
    }

    public function input(string $name, array $attrs = [], string $label = '', mixed $value = null)
    {
        if (!isset($attrs['type'])) {
            $attrs['type'] = 'text';
        }
        if (!is_null($label)) {
            $label = $label ? $label : $this->createLabel($name);
            $attrs['placeholder'] = isset($attrs['placeholder']) ? $attrs['placeholder'] : $label;
        }
        $attrs['id'] = str_replace(['[', ']'], '_', $name) . 'Input';
        $attrs['value'] = is_null($value) ? $this->getValue($name) : $value;
        return (is_null($label) ? '' : '<label for="' . $attrs['id'] . '">' . ($label ? $label : $this->createLabel($name)) . (isset($attrs['required']) ? ' *' : '') . '</label>') . '<input name="' . $name . '"' . $this->makeAttributes($attrs) . ' />';
    }

    public function select(string $name, mixed $options, array $attrs = [], string $label = '', mixed $value = null)
    {
        if (!is_null($label)) {
            $label = $label ? $label : $this->createLabel($name);
        }
        $attrs['id'] = str_replace(['[', ']'], '_', $name) . 'Input';
        $options = $this->createOptions($options, '--- Select ' . $label . ' ---', is_null($value) ? $this->getValue($name) : $value);
        return (is_null($label) ? '' : '<label for="' . $attrs['id'] . '">' . ($label ? $label : $this->createLabel($name)) . (isset($attrs['required']) ? ' *' : '') . '</label>') . '<select name="' . $name . '"' . $this->makeAttributes($attrs) . '>' . $options . '</select>';
    }

    public function textarea(string $name, array $attrs = [], string $label = '', mixed $value = null)
    {
        if (!isset($attrs['type'])) {
            $attrs['type'] = 'text';
        }
        if (!is_null($label)) {
            $label = $label ? $label : $this->createLabel($name);
            $attrs['placeholder'] = isset($attrs['placeholder']) ? $attrs['placeholder'] : $label;
        }
        $attrs['id'] = str_replace(['[', ']'], '_', $name) . 'Input';
        return (is_null($label) ? '' : '<label for="' . $attrs['id'] . '">' . ($label ? $label : $this->createLabel($name)) . (isset($attrs['required']) ? ' *' : '') . '</label>') . '<textarea name="' . $name . '"' . $this->makeAttributes($attrs) . '>' . (is_null($value) ? $this->getValue($name) : $value) . '</textarea>';
    }
}
