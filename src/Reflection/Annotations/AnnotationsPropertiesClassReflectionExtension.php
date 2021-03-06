<?php declare(strict_types = 1);

namespace PHPStan\Reflection\Annotations;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Type\FileTypeMapper;

class AnnotationsPropertiesClassReflectionExtension implements PropertiesClassReflectionExtension
{

	/** @var \PHPStan\Type\FileTypeMapper */
	private $fileTypeMapper;

	/** @var \PHPStan\Reflection\PropertyReflection[][] */
	private $properties = [];

	public function __construct(FileTypeMapper $fileTypeMapper)
	{
		$this->fileTypeMapper = $fileTypeMapper;
	}

	public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
	{
		if (!isset($this->properties[$classReflection->getName()])) {
			$this->properties[$classReflection->getName()] = $this->createProperties($classReflection, $classReflection);
		}

		return isset($this->properties[$classReflection->getName()][$propertyName]);
	}

	public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
	{
		return $this->properties[$classReflection->getName()][$propertyName];
	}

	/**
	 * @param \PHPStan\Reflection\ClassReflection $classReflection
	 * @param \PHPStan\Reflection\ClassReflection $declaringClass
	 * @return \PHPStan\Reflection\PropertyReflection[]
	 */
	private function createProperties(
		ClassReflection $classReflection,
		ClassReflection $declaringClass
	): array
	{
		$properties = [];
		foreach ($classReflection->getTraits() as $traitClass) {
			$properties += $this->createProperties($traitClass, $classReflection);
		}
		foreach ($classReflection->getParents() as $parentClass) {
			$properties += $this->createProperties($parentClass, $parentClass);
			foreach ($parentClass->getTraits() as $traitClass) {
				$properties += $this->createProperties($traitClass, $parentClass);
			}
		}

		foreach ($classReflection->getInterfaces() as $interfaceClass) {
			$properties += $this->createProperties($interfaceClass, $interfaceClass);
		}

		$fileName = $classReflection->getNativeReflection()->getFileName();
		if ($fileName === false) {
			return $properties;
		}

		$docComment = $classReflection->getNativeReflection()->getDocComment();
		if ($docComment === false) {
			return $properties;
		}

		$typeMap = $this->fileTypeMapper->getTypeMap($fileName);

		preg_match_all('#@property(-read|-write)?\s+' . FileTypeMapper::TYPE_PATTERN . '\s+\$([a-zA-Z0-9_]+)#', $docComment, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$typeString = $match[2];
			if (!isset($typeMap[$typeString])) {
				continue;
			}
			$readable = $writable = true;
			if ($match[1] === '-read') {
				$writable = false;
			} elseif ($match[1] === '-write') {
				$readable = false;
			}
			$type = $typeMap[$typeString];
			$properties[$match[3]] = new AnnotationPropertyReflection($classReflection, $type, $readable, $writable);
		}

		return $properties;
	}

}
