<?php namespace Philo\Translate\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use Philo\Translate\TranslateManager;

class diggCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'translate:digg';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Try to digg all missing translation from application';

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
		$this->manager->disableLoadGroupBuffer();
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$this->info('It\'s time to DIGG for some translations!');
		$this->info(' ');
		foreach ($this->manager->getDiggFiles() as $file) {
			
			$this->comment("Digging translations for '".str_replace(base_path().'/', '', $file)."'");

			foreach ($this->getTranlations($file) as $translate) {
				foreach($this->manager->getLanguages() as $language) {
					$this->manager->setLanguage($language);
					if(\Lang::get($translate['lang_query']) == $translate['lang_query']) {
						if(is_null($translation = $this->ask("Translate '{$translate['lang_query']}' {$translate['parameters']} in " . strtoupper($language) . ": "))) continue;

						if(trim($translation)!='') {
							$this->manager->setLanguage($language)->addLine($translate['group'], $translate['line'], $translation);
						}
					}
				}
			}
		}
	}

	/**
	 * undocumented function
	 *
	 * @return void
	 * @author 
	 **/
	public function getTranlations($file)
	{
		$data = \File::get($file);

		
		//	Try to pick up all Lang funktions from file
		preg_match_all('/Lang::get\(([^\)]*)\)/iU', $data, $matches, PREG_PATTERN_ORDER);
		
		//	return empty array if none found
		if(empty($matches[1])) {
			return array();
		}
			
		//	return unique translations
		$files = array_unique($matches[1]);

		//	clean up
		foreach ($files as &$item) {

			//	separate parameters path
			preg_match('/,\s*\t*(\[.*\])/i', $item, $parts);
			if(!empty($parts)) {
				$item = str_replace($parts[0], '', $item);
				$parameters = $parts[1];
			}
			else {
				$parameters = '';
			}
			
			
			$_i = trim(str_replace(['\'', '"'], '', $item));
			$item = [
				'lang_query'	=> $_i,
				'group' 		=> substr($_i, 0, strpos($_i, '.')),
				'line' 			=> substr($_i, strpos($_i, '.')+1),
				'parameters' 	=> $parameters,
			];
		}
		
		
		return $files;
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
		return array();
	}

}
