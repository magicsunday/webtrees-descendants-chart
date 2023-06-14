<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
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
use MagicSunday\Webtrees\DescendantsChart\Model\Node;
use MagicSunday\Webtrees\DescendantsChart\Model\NodeData;
use MagicSunday\Webtrees\ModuleBase\Processor\DateProcessor;
use MagicSunday\Webtrees\ModuleBase\Processor\ImageProcessor;
use MagicSunday\Webtrees\ModuleBase\Processor\NameProcessor;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;

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
     *
     * @var ModuleCustomInterface
     */
    private ModuleCustomInterface $module;

    /**
     * The configuration instance.
     *
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * @var string
     */
    private string $route;

    /**
     * @param ModuleCustomInterface $module
     *
     * @return DataFacade
     */
    public function setModule(ModuleCustomInterface $module): DataFacade
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
     * Creates the JSON tree structure.
     *
     * @param Individual $individual
     *
     * @return array<string, Node[]>
     */
    public function createTreeStructure(Individual $individual): array
    {
        $tree = $this->buildTreeStructure($individual);

        // We need to post-process the data to correct the structure for some special cases
        $tree = $this->postProcessTreeStructure($tree);

        // Return the root node with all children
        return [
            'children' => $tree,
        ];
    }

    /**
     * Post process the tree structure.
     *
     * @param Node[] $root
     *
     * @return Node[]
     */
    private function postProcessTreeStructure(array $root): array
    {
        /** @var RecursiveIteratorIterator<RecursiveArrayIterator<int, Node>> $recursiveIterator */
        $recursiveIterator = new RecursiveIteratorIterator(
            new RecursiveArrayIterator($root),
            RecursiveIteratorIterator::SELF_FIRST
        );

        if ($this->configuration->getHideSpouses()) {
            $this->sortChildrenByBirthdate($recursiveIterator);
        }

        /** @var RecursiveArrayIterator<int|string, Node> $innerIterator */
        $innerIterator = $recursiveIterator->getInnerIterator();

        return $innerIterator->getArrayCopy();
    }

    /**
     * Sort the list of children by their date of birth.
     *
     * If the spouses are not displayed, the children in a polygamous family may not be sorted correctly,
     * for example, if there are several children with different partners who have mixed dates of birth.
     *
     * @param RecursiveIteratorIterator<RecursiveArrayIterator<int, Node>> $recursiveIterator
     *
     * @return void
     */
    private function sortChildrenByBirthdate(RecursiveIteratorIterator $recursiveIterator): void
    {
        /** @var Node $item */
        foreach ($recursiveIterator as $item) {
            $childrenCollection = new Collection($item->getChildren());

            // Check if each individual in the child list has a valid birthdate
            if (
                $childrenCollection->every(
                    static function (Node $nodeData): bool {
                        return ($nodeData->getData()->getIndividual() !== null)
                            && $nodeData->getData()->getIndividual()->getBirthDate()->isOK();
                    }
                )
            ) {
                $item->setChildren(
                    $childrenCollection
                        ->sort($this->birthDateComparator())
                        ->values()
                        ->toArray()
                );
            }
        }
    }

    /**
     * A closure which will compare individuals by birthdate.
     *
     * @return Closure(Node, Node):int
     */
    private function birthDateComparator(): Closure
    {
        return static function (Node $nodeData1, Node $nodeData2): int {
            return Date::compare(
                $nodeData1->getData()->getIndividual() !== null
                    ? $nodeData1->getData()->getIndividual()->getEstimatedBirthDate()
                    : new Date(''),
                $nodeData2->getData()->getIndividual() !== null
                    ? $nodeData2->getData()->getIndividual()->getEstimatedBirthDate()
                    : new Date('')
            );
        };
    }

    /**
     * Recursively build the data array of the individual ancestors.
     *
     * @param null|Individual $individual The start person
     * @param int             $generation The current generation
     *
     * @return Node[]
     */
    private function buildTreeStructure(?Individual $individual, int $generation = 1): array
    {
        // Maximum generation reached
        if (($individual === null) || ($generation > $this->configuration->getGenerations())) {
            return [];
        }

        // Get spouse families
        $families = $individual->spouseFamilies();

        $nodes = [];
        $nodes[$individual->xref()] = new Node(
            $this->getNodeData($generation, $individual)
        );

        if ($families->count() > 0) {
            /** @var Family $family */
            foreach ($families as $familyIndex => $family) {
                $children = [];
                $spouse   = null;

                if (!$this->configuration->getHideSpouses()) {
                    $spouse = $family->spouse($individual);
                }

                foreach ($family->children() as $child) {
                    $childTree = $this->buildTreeStructure($child, $generation + 1);

                    foreach ($childTree as $childData) {
                        $children[$childData->getData()->getId()] = $childData;
                    }
                }

                // If there is more than one family for an individual, but not all spouses are known, a
                // "fake" spouse must still be added here. So that when drawing the tree, the children
                // can be assigned to the correct family and spouse.
                if (
                    !$this->configuration->getHideSpouses()
                    && (
                        ($spouse !== null)
                        || ($families->count() > 1)
                    )
                ) {
                    $node = new Node(
                        $this->getNodeData($generation, $spouse, $individual)
                    );

                    $node
                        ->setSpouse($nodes[$individual->xref()]->getData()->getId())
                        ->setFamily($familyIndex)
                        ->setChildren(array_values($children));

                    $nodes[] = $node;

                    // Add spouse to list
                    $nodes[$individual->xref()]->addSpouse($node->getData()->getId());
                } else {
                    // If there is no spouse, merge all children from all families
                    // of the individual into one list
                    $nodes[$individual->xref()]
                        ->setFamily($familyIndex)
                        ->setChildren(
                            array_merge(
                                $nodes[$individual->xref()]->getChildren(),
                                array_values($children)
                            )
                        );
                }
            }
        }

        return array_values($nodes);
    }

    /**
     * Get the node data required for display the chart.
     *
     * @param int             $generation The generation the person belongs to
     * @param null|Individual $individual The current individual
     * @param null|Individual $spouse
     *
     * @return NodeData
     */
    private function getNodeData(
        int $generation,
        Individual $individual = null,
        Individual $spouse = null
    ): NodeData {
        // Create a unique ID for each individual
        static $id = 0;

        $treeData = new NodeData();
        $treeData->setId(++$id)
            ->setGeneration($generation);

        if ($individual === null) {
            return $treeData;
        }

        $nameProcessor = new NameProcessor(
            $individual,
            $spouse,
            $this->configuration->getShowMarriedNames()
        );

        $dateProcessor  = new DateProcessor($individual);
        $imageProcessor = new ImageProcessor($this->module, $individual);

        $fullNN          = $nameProcessor->getFullName();
        $alternativeName = $nameProcessor->getAlternateName($individual);

        $treeData
            ->setXref($individual->xref())
            ->setUrl($individual->url())
            ->setUpdateUrl($this->getUpdateRoute($individual))
            ->setName($fullNN)
            ->setIsNameRtl($this->isRtl($fullNN))
            ->setFirstNames($nameProcessor->getFirstNames())
            ->setLastNames($nameProcessor->getLastNames())
            ->setPreferredName($nameProcessor->getPreferredName())
            ->setAlternativeName($alternativeName)
            ->setIsAltRtl($this->isRtl($alternativeName))
            ->setThumbnail($imageProcessor->getHighlightImageUrl())
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
        array $parameters = []
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
