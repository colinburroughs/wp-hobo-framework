<?php

use Hobo\Framework\Html_Helper;
use PHPUnit\Framework\TestCase;

class Html_HelperTest extends TestCase
{

    /**
     * @dataProvider dataProviderSelect
     */
    public function testSelectOptionsFromArray($source, $selected, $use_key_as_value, $expected)
    {
        $clazz = new Html_Helper();
        $this->expectOutputString($expected);
        $clazz->select_options_from_array($source, $selected, $use_key_as_value);
    }

    /**
     * @dataProvider dataProviderRadio
     */
    public function testRadioFromArray($source, $name, $checked, $use_key_as_value, $separator, $expected)
    {
        $clazz = new Html_Helper();
        $this->expectOutputString($expected);
        $clazz->radio_from_array($source, $name, $checked, $use_key_as_value, $separator);
    }

    /**
     * @dataProvider dataProviderCheckbox
     */
    public function testCheckboxFromArray($source, $name, $checked, $use_key_as_value, $separator, $expected) {
        $clazz = new Html_Helper();
        $this->expectOutputString($expected);
        $clazz->checkbox_from_array($source, $name, $checked, $use_key_as_value, $separator);
    }

    public function dataProviderSelect()
    {
        return [
            "data1" => [['a' => 'A', 'b' => 'B'], NULL, TRUE, '<option value="a">A<option value="b">B'],
            "data2" => [['a' => 'A', 'b' => 'B'], NULL, FALSE, '<option value="A">A<option value="B">B'],
            "data3" => [['a' => 'A', 'b' => 'B'], 'b', TRUE, '<option value="a">A<option value="b" selected>B'],
            "data4" => [['a' => 'A', 'b' => 'B'], 'B', FALSE, '<option value="A">A<option value="B" selected>B']
        ];
    }

    public function dataProviderRadio()
    {
        return [
            "data1" => [['a' => 'A', 'b' => 'B'], 'test', NULL, TRUE, '&nbsp;', '<input name="test" type="radio" value="a">A&nbsp;<input name="test" type="radio" value="b">B'],
            "data2" => [['a' => 'A', 'b' => 'B'], 'test', NULL, FALSE, '&nbsp;', '<input name="test" type="radio" value="A">A&nbsp;<input name="test" type="radio" value="B">B'],
            "data3" => [['a' => 'A', 'b' => 'B'], 'test', 'b', TRUE, '&nbsp;', '<input name="test" type="radio" value="a">A&nbsp;<input name="test" type="radio" value="b" checked>B'],
            "data4" => [['a' => 'A', 'b' => 'B'], 'test', 'B', FALSE, '&nbsp;', '<input name="test" type="radio" value="A">A&nbsp;<input name="test" type="radio" value="B">B'],
            "data5" => [['a' => 'A', 'b' => 'B'], 'test', 'B', FALSE, '|', '<input name="test" type="radio" value="A">A|<input name="test" type="radio" value="B">B']
        ];
    }

    public function dataProviderCheckbox()
    {
        return [
            "data1" => [['a' => 'A', 'b' => 'B'], 'test', NULL, TRUE, '&nbsp;', '<input name="test" type="checkbox" value="a">A&nbsp;<input name="test" type="checkbox" value="b">B'],
            "data2" => [['a' => 'A', 'b' => 'B'], 'test', NULL, FALSE, '&nbsp;', '<input name="test" type="checkbox" value="A">A&nbsp;<input name="test" type="checkbox" value="B">B'],
            "data3" => [['a' => 'A', 'b' => 'B'], 'test', 'b', TRUE, '&nbsp;', '<input name="test" type="checkbox" value="a">A&nbsp;<input name="test" type="checkbox" value="b" checked>B'],
            "data4" => [['a' => 'A', 'b' => 'B'], 'test', 'B', FALSE, '&nbsp;', '<input name="test" type="checkbox" value="A">A&nbsp;<input name="test" type="checkbox" value="B" checked>B'],
            "data5" => [['a' => 'A', 'b' => 'B'], 'test', 'B', FALSE, '|', '<input name="test" type="checkbox" value="A">A|<input name="test" type="checkbox" value="B" checked>B'],
            "data6" => [['a' => 'A', 'b' => 'B'], 'test', ['A','B'], FALSE, '|', '<input name="test" type="checkbox" value="A" checked>A|<input name="test" type="checkbox" value="B" checked>B'],
            "data7" => [['a' => 'A', 'b' => 'B'], 'test', ['a','b'], TRUE, '|', '<input name="test" type="checkbox" value="a" checked>A|<input name="test" type="checkbox" value="b" checked>B']
        ];
    }
}