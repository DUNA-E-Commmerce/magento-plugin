<?php

namespace DUna\Payments\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Sandobox implements ArrayInterface
{
    /*
     * Option getter
     * @return array
     */
    public function toOptionArray()
    {
        $arr = $this->toArray();
        $ret = [];
        foreach ($arr as $key => $value) {
            $ret[] = [
                'value' => $key,
                'label' => $value
            ];
        }
        return $ret;
    }

    /*
     * Get options in "key-value" format
     * @return array
     */
    public function toArray()
    {
        $choose = [
            '1' => 'Sandbox',
            '2' => 'Live'
        ];
        return $choose;
    }
}
