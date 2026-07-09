<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;

/**
 * Garante 3 slides ativos em homepageslider com imagens existentes em pub/media.
 */
class HomepagesliderBackfiller
{
    private const SLIDER_IDENTIFIER = 'homepageslider';

    private const SLIDER_SETTING = '{"items":1,"itemsDesktop":"[1199,1]","itemsDesktopSmall":"[980,1]","itemsTablet":"[768,1]","itemsMobile":"[479,1]","slideSpeed":500,"paginationSpeed":500,"rewindSpeed":500,"autoPlay":5000,"navigation":true,"pagination":true}';

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger
    ) {
    }

    public function backfill(AdapterInterface $connection): void
    {
        $sliderTable = 'rokanthemes_slider';
        $slideTable = 'rokanthemes_slide';

        if (!$connection->isTableExists($sliderTable) || !$connection->isTableExists($slideTable)) {
            $this->logger->warning('[HomepagesliderBackfiller] Tabelas SlideBanner ausentes.');
            return;
        }

        $sliderId = $connection->fetchOne(
            $connection->select()
                ->from($sliderTable, ['slider_id'])
                ->where('slider_identifier = ?', self::SLIDER_IDENTIFIER)
        );

        if (!$sliderId) {
            $connection->insert($sliderTable, [
                'slider_title' => 'Homepage Slider - AWA Motos',
                'slider_identifier' => self::SLIDER_IDENTIFIER,
                'slider_status' => 1,
                'store_ids' => '0',
                'slider_setting' => self::SLIDER_SETTING,
            ]);
            $sliderId = (int) $connection->lastInsertId($sliderTable);
            $this->logger->info('[HomepagesliderBackfiller] Slider homepageslider criado.');
        } else {
            $sliderId = (int) $sliderId;
            $connection->update(
                $sliderTable,
                [
                    'slider_status' => 1,
                    'slider_setting' => self::SLIDER_SETTING,
                ],
                ['slider_id = ?' => $sliderId]
            );
        }

        $mediaDir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $expected = $this->getExpectedSlides($sliderId, $mediaDir);

        $activeCount = (int) $connection->fetchOne(
            $connection->select()
                ->from($slideTable, ['COUNT(*)'])
                ->where('slider_id = ?', $sliderId)
                ->where('slide_status = ?', 1)
        );

        $connection->update(
            $slideTable,
            ['slide_position' => 1],
            [
                'slider_id = ?' => $sliderId,
                'slide_position = ?' => 0,
                'slide_status = ?' => 1,
            ]
        );

        foreach ($expected as $row) {
            $position = (int) $row['slide_position'];
            $slideId = $connection->fetchOne(
                $connection->select()
                    ->from($slideTable, ['slide_id'])
                    ->where('slider_id = ?', $sliderId)
                    ->where('slide_position = ?', $position)
                    ->where('slide_status = ?', 1)
                    ->limit(1)
            );

            if ($slideId) {
                unset($row['slide_position']);
                $connection->update($slideTable, $row, ['slide_id = ?' => (int) $slideId]);
                continue;
            }

            $connection->insert($slideTable, $row);
        }

        $finalCount = (int) $connection->fetchOne(
            $connection->select()
                ->from($slideTable, ['COUNT(*)'])
                ->where('slider_id = ?', $sliderId)
                ->where('slide_status = ?', 1)
        );

        $this->logger->info(
            sprintf(
                '[HomepagesliderBackfiller] homepageslider: %d slide(s) ativo(s) (antes: %d).',
                $finalCount,
                $activeCount
            )
        );

        // Slider legado duplicado — manter desativado
        $connection->update(
            $sliderTable,
            ['slider_status' => 2],
            ['slider_identifier = ?' => 'homepage5slider']
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getExpectedSlides(int $sliderId, \Magento\Framework\Filesystem\Directory\ReadInterface $mediaDir): array
    {
        $slides = [
            [
                'slide_position' => 1,
                'slide_image' => 'slidebanner/s/l/slider_guidao_cb_300.jpg',
                'slide_image_mobile' => 'slidebanner/s/l/slider_guidao_cb_300.jpg',
                'slide_link' => '/guidoes.html',
                'slide_text' => null,
            ],
            [
                'slide_position' => 2,
                'slide_image' => 'slidebanner/b/a/bauletos_1.jpg',
                'slide_image_mobile' => 'slidebanner/b/a/bauletos_1.jpg',
                'slide_link' => '/bauletos.html',
                'slide_text' => null,
            ],
            [
                'slide_position' => 3,
                'slide_image' => 'slidebanner/s/l/slider_005.jpg',
                'slide_image_mobile' => 'slidebanner/s/l/slider_005_mobile.jpg',
                'slide_link' => '/b2b/register',
                'slide_text' => '<div class="slide-content slide-3"><div class="slide-text-wrap"><h2 class="slide-title">Atacado para Lojistas e Oficinas</h2><p class="slide-desc">Cadastre-se no programa B2B e tenha preços especiais</p><a href="/b2b/register" class="slide-btn btn btn-primary">Cadastro B2B</a></div></div>',
            ],
        ];

        $out = [];
        foreach ($slides as $slide) {
            $desktop = (string) $slide['slide_image'];
            $mobile = (string) ($slide['slide_image_mobile'] ?? $desktop);

            if (!$mediaDir->isExist($desktop)) {
                $this->logger->warning(
                    sprintf('[HomepagesliderBackfiller] Imagem ausente, slide ignorado: %s', $desktop)
                );
                continue;
            }

            if (!$mediaDir->isExist($mobile)) {
                $mobile = $desktop;
            }

            $out[] = [
                'slider_id' => $sliderId,
                'slide_type' => 1,
                'slide_status' => 1,
                'slide_position' => (int) $slide['slide_position'],
                'slide_image' => $desktop,
                'slide_image_mobile' => $mobile,
                'slide_link' => $slide['slide_link'],
                'slide_text' => $slide['slide_text'],
            ];
        }

        return $out;
    }
}
