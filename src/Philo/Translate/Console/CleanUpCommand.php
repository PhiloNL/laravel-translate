<?php namespace Philo\Translate\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use Philo\Translate\TranslateManager;

class CleanUpCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'translate:cleanup';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Find and remove language strings that are not being used';

	protected $progress;
	protected $missing = array();

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct(TranslateManager $manager)
	{
		parent::__construct();
		$this->manager  = $manager;
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$this->progress = $this->getHelperSet()->get('progress');

		$this->info('Time to clean!');
		$this->comment("Please note that this make take a while depending on the amount of translations...\n");

		if(is_null($files = $this->manager->getLanguageFiles()))
		{
			return $this->error('No translation files where found.');
		}

		if($group = $this->input->getOption('group'))
		{
			if($lines = array_get($files, $group))
			{
				$this->processGroup($group, $lines);
			}
			else
			{
				return $this->error('Given group does not exist.');
			}
		}
		else
		{
			foreach($files as $group => $lines)
			{
				$this->processGroup($group, $lines);
			}
		}
	}


	protected function processGroup($group, $lines)
	{	
		//	Do nothig if there is no line
		if(empty($lines) || is_string($lines)) return ;

		array_set($this->missing, $group, array());
		
		$this->line("Processing $group group...");
		$this->progress->start($this->output, count($lines, COUNT_RECURSIVE));

		//	Iterate over passed lines
		foreach ($lines as $line => $translation)
		{
			//	Recurse if lines are grou
			if(is_array($translation)) {
				$this->processGroup($group.'.'.$line, $line);
			}

			//	Process translation
			else {
				
				if($this->manager->findLineCount($group, $line) == 0) {
				
					array_push($this->missing[$group], $line);
				}

				$this->progress->advance();
			}
		}

		$this->line(" "); // Add line break to stop lines from joining
		//var_dump($this->missing, array_get($this->missing, $group));
		if($missing = array_get($this->missing, $group))
		{
			
			foreach($missing as $m)
			{
				if( $this->input->getOption('silent') OR $confirm = $this->confirm("Remove $group.$m ?"))
				{
					$this->manager->removeLine($group, $m);
					$this->info("Removed $m from $group");
				}
			}
		}

	}

	protected function _processGroup($group, $lines)
	{
		if(empty($lines) || is_string($lines)) continue;
		array_set($this->missing, $group, array());

		$this->line("Processing $group group...");
		$this->progress->start($this->output, count($lines));

		foreach ($lines as $line => $translation)
		{
			if($this->manager->findLineCount($group, $line) == 0)
					array_push($this->missing[$group], $line);

			$this->progress->advance();
		}

		$this->line(" "); // Add line break to stop lines from joining

		if($missing = array_get($this->missing, $group))
		{
			foreach($missing as $m)
			{
				if( $this->input->getOption('silent') OR $confirm = $this->confirm("Remove $group.$m ?"))
				{
					$this->manager->removeLine($group, $m);
					$this->info("Removed $m from $group");
				}
			}
		}

		$this->info("\nFinished processing $group group...\n");
	}


	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array();
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			array('group', null, InputOption::VALUE_OPTIONAL, 'Clean specific group.'),
			array('silent', null, InputOption::VALUE_NONE, 'Remove missing lines without confirmation'),
		);
	}

}
