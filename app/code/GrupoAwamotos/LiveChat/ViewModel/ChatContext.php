<?php

declare(strict_types=1);

namespace GrupoAwamotos\LiveChat\ViewModel;

use GrupoAwamotos\LiveChat\Model\Chat\PageContextBuilder;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class ChatContext implements ArgumentInterface
{
    private PageContextBuilder $pageContextBuilder;

    private Json $jsonSerializer;

    public function __construct(
        PageContextBuilder $pageContextBuilder,
        Json $jsonSerializer
    ) {
        $this->pageContextBuilder = $pageContextBuilder;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * Return page variables used by the LiveChat widget.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public function getPageVariables(): array
    {
        return $this->pageContextBuilder->build();
    }

    /**
     * Return page variables serialized as JSON for the frontend snippet.
     */
    public function getPageVariablesJson(): string
    {
        return $this->jsonSerializer->serialize($this->getPageVariables());
    }
}
