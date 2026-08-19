<?php

namespace App\Controller\Admin\Fields;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Contracts\Translation\TranslatableInterface;

final class CKEditorField implements FieldInterface
{
    use FieldTrait;

    public static function new(
        string $propertyName,
        bool|null|string|TranslatableInterface $label = null
    ): self {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(CKEditorType::class);
    }
}
