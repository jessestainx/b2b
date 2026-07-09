<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Model\Config\Backend;

use Magento\Framework\App\Config\Value;

class LastSync extends Value
{
    public function afterLoad()
    {
        $value = $this->getValue();
        if (empty($value)) {
            $this->setValue(__('Nunca sincronizado'));
        }
        return parent::afterLoad();
    }
}
