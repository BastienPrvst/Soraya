<?php

namespace App\Controller\Admin\Filters;

use App\Enum\OrderStatus;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class OrderStatusFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName, $label = null): self
    {
        $choices = array_combine(
            array_map(fn(OrderStatus $s) => $s->label(), OrderStatus::cases()),
            array_map(fn(OrderStatus $s) => $s->value, OrderStatus::cases())
        );

        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ChoiceFilterType::class)
            ->setFormTypeOption('value_type', ChoiceType::class)
            ->setFormTypeOption('value_type_options', [
                'choices'  => $choices,
                'multiple' => true,
                'expanded' => true,
                'choice_attr' => array_combine(
                    array_map(fn(OrderStatus $s) => $s->label(), OrderStatus::cases()),
                    array_map(fn(OrderStatus $s) => [
                        'class' => match ($s) {
                            OrderStatus::PAID      => 'bg-primary',
                            OrderStatus::SHIPPED => 'bg-success',
                            OrderStatus::REFUND    => 'bg-warning',
                            OrderStatus::CANCELLED  => 'bg-danger',
                            default         => 'bg-secondary',
                        }
                    ], OrderStatus::cases())
                ),
            ]);
    }

    public function apply(
        QueryBuilder $queryBuilder,
        FilterDataDto $filterDataDto,
        ?FieldDto $fieldDto,
        EntityDto $entityDto
    ): void {
        $value = $filterDataDto->getValue();

        if (empty($value)) {
            return;
        }

        $alias = $filterDataDto->getEntityAlias();
        $property = $filterDataDto->getProperty();

        if (is_array($value)) {
            $queryBuilder
                ->andWhere(sprintf('%s.%s IN (:statuses)', $alias, $property))
                ->setParameter('statuses', $value);
        } else {
            $queryBuilder
                ->andWhere(sprintf('%s.%s = :status', $alias, $property))
                ->setParameter('status', $value);
        }
    }
}
