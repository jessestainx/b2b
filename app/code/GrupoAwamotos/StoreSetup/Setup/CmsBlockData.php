<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Setup;

final class CmsBlockData
{
    /**
     * Conteúdo Schema.org para a homepage.
     *
     * Observação importante: usamos aspas simples dentro das diretivas ({{store ...}}/{{media ...}}/{{config ...}})
     * para não quebrar strings JSON que usam aspas duplas.
     */
    public static function schemaOrgHomepageContent(): string
    {
        return <<<HTML
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Como confirmo se a peça serve na minha moto?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Use a busca por aplicação e confira a descrição do produto. Se ficar em dúvida, chame no WhatsApp para confirmar compatibilidade: https://wa.me/5516997367588"
      }
    },
    {
      "@type": "Question",
      "name": "Onde vejo prazo e valor do frete?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "O prazo e o valor são calculados no carrinho/checkout, conforme CEP e itens do pedido."
      }
    },
    {
      "@type": "Question",
      "name": "Como funcionam trocas e devoluções?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Trocas e devoluções seguem a política da loja. Veja detalhes na página de Ajuda/Atendimento."
      }
    },
    {
      "@type": "Question",
      "name": "Quero comprar para revenda (B2B). Como faço?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Faça seu cadastro B2B e, se preferir, envie uma solicitação de cotação."
      }
    }
  ]
}
</script>
HTML;
    }

    /**
     * Banners promocionais de produto — homepage (CMS Block home_product_promo_banners).
     */
    public static function homeProductPromoBannersContent(): string
    {
        $items = [
            [
                'slug'  => 'bagageiro-titan-160',
                'url'   => 'bagageiro-cg-160-titan-modelo-25-preto-macico.html',
                'label' => 'Bagageiro Titan 160 — Confira',
                'id'    => 'promo-3122',
                'name'  => 'Bagageiro Titan 160',
                'slot'  => 1,
            ],
            [
                'slug'  => 'guidao-xtz-250',
                'url'   => 'guidoes.html',
                'label' => 'Guidão XTZ 250 Lander — Confira',
                'id'    => 'promo-2390',
                'name'  => 'Guidão XTZ 250',
                'slot'  => 2,
            ],
            [
                'slug'  => 'guidao-factor',
                'url'   => 'guidoes.html',
                'label' => 'Guidão Factor 150/125 — Confira',
                'id'    => 'promo-2391',
                'name'  => 'Guidão Factor',
                'slot'  => 3,
            ],
        ];

        $html = '<div class="awa-product-promo-banners__grid awa-reel awa-reel--sm">';

        foreach ($items as $item) {
            $html .= self::homeProductPromoBannerItemHtml($item);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param array{slug: string, url: string, label: string, id: string, name: string, slot: int} $item
     */
    private static function homeProductPromoBannerItemHtml(array $item): string
    {
        $slug = $item['slug'];
        $mediaBase = 'wysiwyg/home-banners/promo-' . $slug;
        $sizes = '(min-width:992px) 33vw, (min-width:768px) 33vw, 85vw';

        $avifSrcset = implode(', ', [
            "{{media url='{$mediaBase}-mobile.avif'}} 390w",
            "{{media url='{$mediaBase}-tablet.avif'}} 600w",
            "{{media url='{$mediaBase}.avif'}} 463w",
        ]);
        $webpSrcset = implode(', ', [
            "{{media url='{$mediaBase}-mobile.webp'}} 390w",
            "{{media url='{$mediaBase}-tablet.webp'}} 600w",
            "{{media url='{$mediaBase}.webp'}} 463w",
        ]);

        return <<<HTML
    <a class="awa-product-promo-banners__item"
       href="{{store url='{$item['url']}'}}"
       aria-label="{$item['label']}"
       data-promo-id="{$item['id']}"
       data-promo-name="{$item['name']}"
       data-promo-slot="{$item['slot']}">
        <span class="visually-hidden">{$item['label']}</span>
        <picture>
            <source type="image/avif"
                    srcset="{$avifSrcset}"
                    sizes="{$sizes}" />
            <source type="image/webp"
                    srcset="{$webpSrcset}"
                    sizes="{$sizes}" />
            <img loading="lazy"
                 decoding="async"
                 src="{{media url='{$mediaBase}.webp'}}"
                 alt=""
                 aria-hidden="true"
                 width="463"
                 height="349" />
        </picture>
    </a>

HTML;
    }
}
