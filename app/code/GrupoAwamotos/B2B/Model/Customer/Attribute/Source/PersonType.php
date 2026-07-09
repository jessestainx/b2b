<?php

/**
 * Person Type Source Model (PF/PJ)
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Customer\Attribute\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class PersonType extends AbstractSource
{
    public const TYPE_PJ = 'pj';
    public const TYPE_PF = 'pf';

    /**
     * Get all options
     *
     * @return array
     */
    public function getAllOptions(): array
    {
        if ($this->_options === null) {
            $this->_options = [
                ['value' => '', 'label' => __('-- Selecione --')],
                ['value' => self::TYPE_PJ, 'label' => __('Pessoa Jurídica (CNPJ)')],
                ['value' => self::TYPE_PF, 'label' => __('Pessoa Física (CPF)')],
            ];
        }
        return $this->_options;
    }

    /**
     * Get option text by value
     *
     * @param string $value
     * @return string|bool
     */
    public function getOptionText($value)
    {
        foreach ($this->getAllOptions() as $option) {
            if ($option['value'] == $value) {
                return $option['label'];
            }
        }
        return false;
    }

    /**
     * Check if is company (PJ)
     *
     * @param string $type
     * @return bool
     */
    public static function isCompany(string $type): bool
    {
        return $type === self::TYPE_PJ;
    }
}
