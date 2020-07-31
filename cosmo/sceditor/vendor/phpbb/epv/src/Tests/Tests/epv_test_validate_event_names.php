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

use Phpbb\Epv\Events\php_exporter;
use Phpbb\Epv\Output\Output;
use Phpbb\Epv\Output\OutputInterface;
use Phpbb\Epv\Tests\BaseTest;

class epv_test_validate_event_names extends BaseTest
{
	public function __construct($debug, OutputInterface $output, $basedir, $namespace, $titania, $opendir)
	{
		parent::__construct($debug, $output, $basedir, $namespace, $titania, $opendir);

		$this->directory = true;
	}

	public function validateDirectory(array $dirList)
	{
		$exporter = new php_exporter($this->output, $this->opendir);

		foreach ($dirList as $file)
		{
			try
			{
				if (substr($file, -4) === '.php')
				{
					$exporter->crawl_php_file($file);
				}
			}
			catch
			(\LogicException $e)
			{
				$this->output->addMessage(Output::FATAL, $e->getMessage());
			}
		}

		$events = $exporter->get_events();
		// event names are required to be lowercase
		// event names should end with a dot to seperate the vendor.name and the actual event name.
		$vendor = strtolower(str_replace('/', '.', $this->namespace)) . '.';

		foreach ($events as $event)
		{
			$event['file'] = str_replace($this->basedir, '', $event['file']);
			if (0 === stripos($event['event'], 'phpbb.'))
			{
				$this->output->addMessage(Output::ERROR, sprintf('The phpbb vendorname should only be used for official extensions in event names in %s. Current event name: %s', $event['file'], $event['event']));
			}
			else if (0 === stripos($event['event'], 'core.'))
			{
				$this->output->addMessage(Output::FATAL, sprintf('The core vendorname should not be used in event names in %s. Current event name: %s', $event['file'], $event['event']));
			}

			$substr = substr($event['event'], 0, strlen($vendor));
			if ($substr != $vendor)
			{
				$this->output->addMessage(Output::NOTICE, sprintf('The event name should start with vendor.namespace (Which is %s) but started with %s in %s', $vendor, $substr, $event['file']));
			}
		}
	}

	public function testName()
	{
		return 'Test event names';
	}
}
