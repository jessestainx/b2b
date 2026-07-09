<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Model;

use Magento\Framework\Model\AbstractModel;

class NetworkRules extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\GrupoAwamotos\RexisML\Model\ResourceModel\NetworkRules::class);
    }

    public function getAntecedent()
    {
        return $this->getData('antecedent');
    }

    public function getConsequent()
    {
        return $this->getData('consequent');
    }

    public function getSupport()
    {
        return $this->getData('support');
    }

    public function getConfidence()
    {
        return $this->getData('confidence');
    }

    public function getLift()
    {
        return $this->getData('lift');
    }

    public function getConviction()
    {
        return $this->getData('conviction');
    }
}
