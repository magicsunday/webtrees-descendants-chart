<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Facade;

use Closure;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Illuminate\Support\Collection;
use MagicSunday\Webtrees\DescendantsChart\Configuration;
use MagicSunday\Webtrees\DescendantsChart\Model\CoupleNode;
use MagicSunday\Webtrees\DescendantsChart\Model\NodeData;
use MagicSunday\Webtrees\ModuleBase\Contract\ModuleAssetUrlInterface;
use MagicSunday\Webtrees\ModuleBase\Processor\DateProcessor;
use MagicSunday\Webtrees\ModuleBase\Processor\ImageProcessor;
use MagicSunday\Webtrees\ModuleBase\Processor\NameProcessor;

/**
 * Facade class to hide complex logic to generate the structure required to display the tree.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
class DataFacade
{
    /**
     * The module.
     */
    private ModuleCustomInterface&ModuleAssetUrlInterface $module;

    /**
     * The configuration instance.
     */
    private Configuration $configuration;

    private string $route;

    /**
     * @param ModuleCustomInterface&ModuleAssetUrlInterface $module
     *
     * @return DataFacade
     */
    public function setModule(ModuleCustomInterface&ModuleAssetUrlInterface $module): DataFacade
    {
        $this->module = $module;

        return $this;
    }

    /**
     * @param Configuration $configuration
     *
     * @return DataFacade
     */
    public function setConfiguration(Configuration $configuration): DataFacade
    {
        $this->configuration = $configuration;

        return $this;
    }

    /**
     * @param string $route
     *
     * @return DataFacade
     */
    public function setRoute(string $route): DataFacade
    {
        $this->route = $route;

        return $this;
    }

    /**
     * Creates the JSON couple-tree structure.
     *
     * The root subject of the chart is wrapped in a single-family pseudo
     * `CoupleNode` so the JSON shape stays uniform: every level is a list of
     * couple nodes inside a `memberFamilies[].children` slot.
     *
     * @return array{members: NodeData[], memberFamilies: array<int, array{family: int, spouseIndex: int|null, children: CoupleNode[]}>}|null
     */
    public function createTreeStructure(Individual $individual): ?array
    {
        $rootCouple = $this->buildCoupleStructure($individual);

        if (!$rootCouple instanceof CoupleNode) {
            return null;
        }

        if ($this->configuration->getHideSpouses()) {
            $this->sortChildrenByBirthdate($rootCouple);
        }

        return $rootCouple->jsonSerialize();
    }

    /**
     * Recursively walk a couple node and order each family's children by
     * birthdate. Only runs in the hide-spouses code path because that is the
     * mode where mixed-family children may otherwise interleave incorrectly.
     */
    private function sortChildrenByBirthdate(CoupleNode $couple): void
    {
        $sorted = [];

        foreach ($couple->getMemberFamilies() as $entry) {
            $sortedChildren = (new Collection($entry['children']))
                ->sort($this->birthDateComparator())
                ->values()
                ->all();

            $sorted[] = [
                'family'      => $entry['family'],
                'spouseIndex' => $entry['spouseIndex'],
                'children'    => $sortedChildren,
            ];

            foreach ($sortedChildren as $child) {
                $this->sortChildrenByBirthdate($child);
            }
        }

        $couple->setSortedFamilies($sorted);
    }

    /**
     * A closure which will compare couple nodes by the real-person's birthdate.
     *
     * @return Closure(CoupleNode, CoupleNode):int
     */
    private function birthDateComparator(): Closure
    {
        return static function (CoupleNode $left, CoupleNode $right): int {
            $leftIndividual  = $left->getMembers()[0]->getIndividual();
            $rightIndividual = $right->getMembers()[0]->getIndividual();

            return Date::compare(
                $leftIndividual instanceof Individual && $leftIndividual->getBirthDate()->isOK()
                    ? $leftIndividual->getEstimatedBirthDate()
                    : new Date(''),
                $rightIndividual instanceof Individual && $rightIndividual->getBirthDate()->isOK()
                    ? $rightIndividual->getEstimatedBirthDate()
                    : new Date('')
            );
        };
    }

    /**
     * Recursively build the couple-tree for a single chart subject.
     *
     * Returns one `CoupleNode` whose first member is `$individual`. Each of
     * the individual's spouse-families is recorded in `memberFamilies`,
     * carrying the matching spouse (also added to `members`) and the recursive
     * couple nodes for the family's children.
     */
    private function buildCoupleStructure(?Individual $individual, int $generation = 1): ?CoupleNode
    {
        if ((!$individual instanceof Individual) || ($generation > $this->configuration->getGenerations())) {
            return null;
        }

        $couple = new CoupleNode($this->getNodeData($generation, $individual));

        $families = $individual->spouseFamilies();

        if ($families->count() === 0) {
            return $couple;
        }

        $hideSpouses    = $this->configuration->getHideSpouses();
        $mergedChildren = [];

        /** @var Family $family */
        foreach ($families as $familyIndex => $family) {
            $children = [];

            foreach ($family->children() as $child) {
                $childCouple = $this->buildCoupleStructure($child, $generation + 1);

                if ($childCouple instanceof CoupleNode) {
                    $children[] = $childCouple;
                }
            }

            if ($hideSpouses) {
                // Polygamous families are merged into a single childless-spouse list later.
                array_push($mergedChildren, ...$children);

                continue;
            }

            $spouse      = $family->spouse($individual);
            $hasSpouse   = $spouse instanceof Individual;
            $needsSpouse = $hasSpouse || ($families->count() > 1);

            if ($needsSpouse) {
                $spouseIndex = $couple->addSpouse(
                    $this->getNodeData($generation, $hasSpouse ? $spouse : null, $individual)
                );
                $couple->addFamily($familyIndex, $spouseIndex, $children);
            } else {
                // Single family with no recorded spouse → record the children
                // directly under the real-person, no spouse entry.
                $couple->addFamily($familyIndex, null, $children);
            }
        }

        if ($hideSpouses) {
            $couple->addFamily(0, null, $mergedChildren);
        }

        return $couple;
    }

    /**
     * Get the node data required for display the chart.
     *
     * @param int             $generation The generation the person belongs to
     * @param Individual|null $individual The current individual
     * @param Individual|null $spouse
     *
     * @return NodeData
     */
    private function getNodeData(
        int $generation,
        ?Individual $individual = null,
        ?Individual $spouse = null,
    ): NodeData {
        // Create a unique ID for each individual
        static $id = 0;

        $treeData = new NodeData();
        $treeData->setId(++$id)
            ->setGeneration($generation);

        if (!$individual instanceof Individual) {
            return $treeData;
        }

        $marriedNamesMode = $this->configuration->getMarriedNamesMode();

        $nameProcessor = new NameProcessor(
            $individual,
            $spouse,
            $marriedNamesMode === Configuration::MARRIED_NAMES_ONLY
        );

        $dateProcessor  = new DateProcessor($individual);
        $imageProcessor = new ImageProcessor($this->module, $individual);

        $showNicknames = $this->configuration->getShowNicknames();
        $fullNN        = $showNicknames
            ? $nameProcessor->getFullNameWithNickname()
            : $nameProcessor->getFullName();
        $alternativeName = $nameProcessor->getAlternateName($individual);
        $lastNames       = $nameProcessor->getLastNames();

        // For "birth + married" mode, append the married surname (in brackets)
        // both to the full-name string and the lastNames array. The JS renderer
        // locates each lastName entry inside the full-name string via indexOf,
        // so the bracketed entry must literally appear in both.
        if ($marriedNamesMode === Configuration::MARRIED_NAMES_BIRTH_AND_MARRIED) {
            $marriedSurnames = $nameProcessor->getMarriedSurnames($spouse);

            if ($marriedSurnames !== []) {
                $marriedSuffix = '(' . implode(' ', $marriedSurnames) . ')';
                $fullNN .= ' ' . $marriedSuffix;
                $lastNames[] = $marriedSuffix;
            }
        }

        $treeData
            ->setXref($individual->xref())
            ->setUrl($individual->url())
            ->setUpdateUrl($this->getUpdateRoute($individual))
            ->setName($fullNN)
            ->setIsNameRtl($this->isRtl($fullNN))
            ->setFirstNames($nameProcessor->getFirstNames())
            ->setLastNames($lastNames)
            ->setPreferredName($nameProcessor->getPreferredName())
            ->setNickname($showNicknames ? $nameProcessor->getNickname() : '')
            ->setAlternativeName($alternativeName)
            ->setIsAltRtl($this->isRtl($alternativeName))
            ->setThumbnail($imageProcessor->getHighlightImageUrl())
            ->setSilhouette($imageProcessor->getSilhouetteUrl())
            ->setSex($individual->sex())
            ->setBirth($dateProcessor->getBirthDate())
            ->setDeath($dateProcessor->getDeathDate())
            ->setTimespan($dateProcessor->getLifetimeDescription())
            ->setIndividual($individual);

        return $treeData;
    }

    /**
     * Get the raw update URL. The "xref" parameter must be the last one as the URL gets appended
     * with the clicked individual id in order to load the required chart data.
     *
     * @param Individual $individual
     *
     * @return string
     */
    private function getUpdateRoute(Individual $individual): string
    {
        return $this->chartUrl(
            $individual,
            [
                'generations' => $this->configuration->getGenerations(),
                'layout'      => $this->configuration->getLayout(),
            ]
        );
    }

    /**
     * @param Individual                $individual
     * @param array<string, int|string> $parameters
     *
     * @return string
     */
    private function chartUrl(
        Individual $individual,
        array $parameters = [],
    ): string {
        return route(
            $this->route,
            [
                'xref' => $individual->xref(),
                'tree' => $individual->tree()->name(),
            ] + $parameters
        );
    }

    /**
     * Returns whether the given text is in RTL style or not.
     *
     * @param string $text The text to check
     *
     * @return bool
     */
    private function isRtl(string $text): bool
    {
        return I18N::scriptDirection(I18N::textScript($text)) === 'rtl';
    }
}
