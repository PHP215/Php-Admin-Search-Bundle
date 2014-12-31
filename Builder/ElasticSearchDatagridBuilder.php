<?php

/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\AdminSearchBundle\Builder;

use Sonata\AdminBundle\Admin\FieldDescriptionInterface;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Builder\DatagridBuilderInterface;
use Sonata\AdminBundle\Datagrid\DatagridInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Sonata\AdminBundle\Datagrid\Datagrid;
use Sonata\AdminBundle\Guesser\TypeGuesserInterface;
use Sonata\AdminSearchBundle\Model\FinderProviderInterface;
use Sonata\AdminSearchBundle\ProxyQuery\ElasticaProxyQuery;
use Elastica\Query;
use Sonata\AdminSearchBundle\Datagrid\Pager;
use Elastica\Query\Builder;

class ElasticSearchDatagridBuilder implements DatagridBuilderInterface
{
    /**
     * For the moment, we assume elasticsearch is used on top of another system.
     * We let part of the job be done by the underlying implementation
     */
    private $databaseDatagridBuilder;
    private $finderProvider;
    private $formFactory;
    private $guesser;

    public function __construct(
        DatagridBuilderInterface $databaseDatagridBuilder,
        FormFactoryInterface $formFactory,
        TypeGuesserInterface $guesser,
        FinderProviderInterface $finderProvider
    ) {
        $this->databaseDatagridBuilder = $databaseDatagridBuilder;
        $this->formFactory             = $formFactory;
        $this->guesser                 = $guesser;
        $this->finderProvider          = $finderProvider;
    }

    /**
     * proxy for the underlying datagrid builder
     *
     * @param \Sonata\AdminBundle\Admin\AdminInterface            $admin
     * @param \Sonata\AdminBundle\Admin\FieldDescriptionInterface $fieldDescription
     *
     * @return void
     */
    public function fixFieldDescription(
        AdminInterface $admin,
        FieldDescriptionInterface $fieldDescription
    ) {
        $this->databaseDatagridBuilder->fixFieldDescription(
            $admin,
            $fieldDescription
        );
    }

    /**
     * {@inheritdoc}
     */
    public function addFilter(DatagridInterface $datagrid, $type = null, FieldDescriptionInterface $fieldDescription, AdminInterface $admin)
    {
        // Try to wrap all types to search types
        $guessType = $this->guesser->guessType($admin->getClass(), $fieldDescription->getName(), $admin->getModelManager());
        $type = $guessType->getType();
        $fieldDescription->setType($type);
        $options = $guessType->getOptions();

        foreach ($options as $name => $value) {
            if (is_array($value)) {
                $fieldDescription->setOption($name, array_merge($value, $fieldDescription->getOption($name, array())));
            } else {
                $fieldDescription->setOption($name, $fieldDescription->getOption($name, $value));
            }
        }

        $admin->addFilterFieldDescription($fieldDescription->getName(), $fieldDescription);

        $fieldDescription->mergeOption('field_options', array('required' => false));
        $filter = $this->filterFactory->create($fieldDescription->getName(), $type, $fieldDescription->getOptions());

        if (!$filter->getLabel()) {
            $filter->setLabel($admin->getLabelTranslatorStrategy()->getLabel($fieldDescription->getName(), 'filter', 'label'));
        }

        $datagrid->addFilter($filter);
    }

    /**
     * @param \Sonata\AdminBundle\Admin\AdminInterface $admin
     * @param array                                    $values
     *
     * @return \Sonata\AdminBundle\Datagrid\DatagridInterface
     */
    public function getBaseDatagrid(
        AdminInterface $admin,
        array $values = array()
    ) {
        $pager = new Pager();

        $defaultOptions = array();
        $defaultOptions['csrf_protection'] = false;

        $formBuilder = $this->formFactory->createNamedBuilder(
            'filter',
            'form',
            array(),
            $defaultOptions
        );

        $proxyQuery = new ElasticaProxyQuery(
            new Builder(), //query builder
            $this->finderProvider->getFinderByAdmin($admin)
        );

        return new Datagrid(
            $proxyQuery,
            $admin->getList(),
            $pager,
            $formBuilder,
            $values
        );
    }
}
