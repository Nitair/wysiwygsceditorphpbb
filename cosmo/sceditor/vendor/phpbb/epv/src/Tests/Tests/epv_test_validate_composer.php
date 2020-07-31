<?php
/**
 *
 * EPV :: The phpBB Forum Extension Pre Validator.
 *
 * @copyright (c) 2014 phpBB Limited <https://www.phpbb.com>
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */
namespace Phpbb\Epv\Tests\Tests;

use Composer\Composer;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Loader\InvalidPackageException;
use Composer\Package\Loader\ValidatingArrayLoader;
use Composer\Package\Version\VersionParser;
use Phpbb\Epv\Files\FileInterface;
use Phpbb\Epv\Files\Type\ComposerFileInterface;
use Phpbb\Epv\Output\Output;
use Phpbb\Epv\Output\OutputInterface;
use Phpbb\Epv\Tests\BaseTest;
use Phpbb\Epv\Tests\Exception\TestException;
use Phpbb\Epv\Tests\Type;

class epv_test_validate_composer extends BaseTest
{

	public function __construct($debug, OutputInterface $output, $basedir, $namespace, $titania, $opendir)
	{
		parent::__construct($debug, $output, $basedir, $namespace, $titania, $opendir);

		$this->fileTypeFull = Type::TYPE_COMPOSER;
	}

	/**
	 * @param FileInterface $file
	 *
	 * @throws \Phpbb\Epv\Tests\Exception\TestException
	 */
	public function validateFile(FileInterface $file)
	{
		if (!$file instanceof ComposerFileInterface)
		{
			throw new TestException('This test expects a php type, but found something else.');
		}
		if (!$file->getJson() || !is_array($file->getJson()))
		{
			throw new TestException('Parsing composer file failed');
		}
		$this->file = $file;

		$this->validateName($file);
		$this->validateLicense($file);
		$this->validateVersion($file);
	}

	/**
	 * Validate if the provided license is the GPL.
	 *
	 * @param \Phpbb\Epv\Files\Type\ComposerFileInterface $file
	 */
	private function validateLicense(ComposerFileInterface $file)
	{
		$json = $file->getJson();
		$this->addMessageIfBooleanTrue(!isset($json['license']), Output::FATAL, 'The license key is missing');
		$this->addMessageIfBooleanTrue(isset($json['license']) && $json['license'] === 'GPL-2.0', Output::WARNING, '"GPL-2.0" is a deprecated SPDX license identifier, use "GPL-2.0-only" instead.');
		$this->addMessageIfBooleanTrue(isset($json['license']) && ($json['license'] !== 'GPL-2.0-only' && $json['license'] !== 'GPL-2.0'), Output::ERROR, 'It is required to use "GPL-2.0-only" as the license identifier. Other licenses are not allowed as per the extension database policies.');
	}

	private function validateName(ComposerFileInterface $file)
	{
		$json = $file->getJson();
		$this->addMessageIfBooleanTrue(!isset($json['name']), Output::FATAL, 'The name key is missing');
		$this->addMessageIfBooleanTrue(isset($json['name']) && strpos($json['name'], '_') !== false, Output::FATAL, 'The namespace should not contain underscores');

	}

	/**
	 * @param ComposerFileInterface $file
	 */
	private function validateVersion(ComposerFileInterface $file)
	{
		$json = $file->getJson();

		if (isset($json['extra']) && isset($json['extra']['soft-require']) && isset($json['extra']['soft-require']['phpbb/phpbb']))
		{
			// https://github.com/phpbb/customisation-db/blob/3.1.x/contribution/extension/type.php#L296
			$regex = '/(<|<=|~|\^|>|>=)([0-9]+(\.[0-9]+)?)\.[*x]/';

			if (preg_match($regex, $json['extra']['soft-require']['phpbb/phpbb']))
			{
				$replace = preg_replace($regex, '$1$2', $json['extra']['soft-require']['phpbb/phpbb']);;
				$this->addMessageIfBooleanTrue(true, Output::ERROR, sprintf('An invalid version constraint is used in soft-require: phpbb/phpbb. You can\'t combine a <|<=|~|\^|>|>= with a *|x. Please replace %s with %s', $json['extra']['soft-require']['phpbb/phpbb'], $replace));
			}
		}

		$parser = new ValidatingArrayLoader(new ArrayLoader(), true, null, ValidatingArrayLoader::CHECK_ALL);
		try {
			$parser->load($json);
		}
		catch (InvalidPackageException $exception)
		{
			$this->handleMessages($exception->getErrors(), Output::FATAL);
			$this->handleMessages($exception->getWarnings(), Output::WARNING);
		}
	}

	/**
	 * Add a array of errors as error into the report
	 *
	 * @param array $errorList
	 * @param int $type
	 */
	private function handleMessages(array $errorList, $type = Output::ERROR)
	{
		foreach ($errorList as $error) {
			$this->output->addMessage($type, 'Composer validation: ' . $error);
		}
	}
	private function addMessageIfBooleanTrue($addMessage, $type, $message)
	{
		if ($addMessage)
		{
			$this->output->addMessage($type, $message, $this->file);
		}
	}

	public function testName()
	{
		return "Validate composer structure";
	}

}
