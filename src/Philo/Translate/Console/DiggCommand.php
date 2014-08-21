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
				
				if(!$translate['valid'] and !$this->confirm('Translation "'.$translate['lang_query'].'" seems wrong. Want to try to translate it anyway? [yes|no]')) {
					continue;
				}

				foreach($this->manager->getLanguages() as $language) {
					$this->manager->setLanguage($language);


					if(\Lang::get($translate['lang_query']) == $translate['lang_query']) {
						if(is_null($translation = $this->ask(($translate['parameters'] ? "NOTICE language guery contains parameters!\n": '')."Translate '{$translate['lang_query']}' in " . strtoupper($language) . ": "))) continue;
						$this->manager->setLanguage($language)->addLine($translate['group'], $translate['line'], $translation, true);
					}
				}

			}
		}
	}

	/**
	 * Parse all translations from files
	 *
	 * @return array
	 * @author 
	 **/
	public function getTranlations($file)
	{
		$data = \File::get($file);

		
		//	Try to pick up all Lang funktions from file
		preg_match_all('/Lang::get\s*\([^;]*\)\s*;/iU', $data, $matches, PREG_PATTERN_ORDER);
		
		//	return empty array if none found
		if(empty($matches[0])) {
			return array();
		}
			
		//	return unique translations
		$files = array_unique($matches[0]);
		
		//	array containing all lang queries
		$lang_queries = [];

		//	clean up
		foreach ($files as $item) {

			$token = token_get_all('<?php '.str_replace(' ', '', $item).'?>');
			foreach ($token as $key => $value) {
				if(is_array($value)) {
					//	We noticed begining of Lang, validate and pick language query
					if(self::getTokenValue($token, $key, T_STRING) === 'Lang') {
						if(self::getTokenValue($token, $key+1, T_DOUBLE_COLON)!==false and self::getTokenValue($token, $key+2, T_STRING) === 'get' and self::getTokenValue($token, $key+4)!==false) {
							$_i = substr(self::getTokenValue($token, $key+4), 1, -1);
							
							$lang_queries[] = [
								'lang_query'	=> $_i,
								'valid'			=> true,
								'group' 		=> substr($_i, 0, strpos($_i, '.')),
								'line' 			=> substr($_i, strpos($_i, '.')+1),
								'parameters' 	=> (self::getTokenValue($token, ($key+5)) == ','),
							];
						
						}
					}
				}
				
			}
			
		}
		
		return $lang_queries;
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getTokenValue( array $tokens, $key, $token=null)
	{	

		//	Our token part is just a string, return string only if no token is defined
		if(!is_array($tokens[$key])) {
			if($token > 0) {
				return false;
			}

			return $tokens[$key];
		}

		if($token>0) {
			return ($tokens[$key][0] == $token ? $tokens[$key][1] : false);
		}

		return $tokens[$key][1];

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
