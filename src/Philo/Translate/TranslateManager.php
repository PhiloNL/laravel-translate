<?php namespace Philo\Translate;

use Illuminate\Filesystem;
use App, Config, Lang, Carbon\Carbon;

class TranslateManager {

	protected $language;
	protected $languages = array();
	protected $loaded    = array();

	public function __construct()
	{
		$this->getLanguages();
	}

	/**
	 * Add language to current instance
	 * @param string $abbreviation
	 */
	public function addLanguage($abbreviation)
	{
		if(in_array($abbreviation, $this->languages)) return;
		array_push($this->languages, $abbreviation);
	}

	/**
	 * Return all available languages
	 * @return array
	 */
	public function getLanguages()
	{
		if( ! empty($this->languages) ) return $this->languages;

		$directories = App::make('Finder')->directories()->in(app_path('lang'));

		// Since we always want the default language to be processed first
		// we add it manually so it will be ignored when looing through all languages
		$this->addLanguage(App::getLocale());

		foreach ($directories as $directory) {
			$this->addLanguage($directory->getfileName());
		}

		return $this->languages;
	}

	/**
	 * Set manager language
	 * @param string $language
	 */
	public function setLanguage($language)
	{
		$this->language = $language;
		App::setLocale($this->language);

		return $this;
	}

	/**
	 * Add new line to translation
	 * @param string $group
	 * @param string $line
	 * @param string $translation
	 */
	public function addLine($group, $line, $translation)
	{
		$lines = $this->loadGroup($group);

		array_set($lines, $line, $translation);
		$this->writeToFile($group, $lines);
	}

	/**
	 * Remove line from namespace
	 * @param  string $group
	 * @param  string $line
	 * @return void
	 */
	public function removeLine($group, $line)
	{
		foreach ($this->languages as $language) {

			$this->setLanguage($language);
			$this->loadGroup($group);

			array_forget($this->loaded, "$language.$group.$line");
			$this->writeToFile($group, array_get($this->loaded, "$language.$group", array()));
		}
	}

	/**
	 * Get variables from translation
	 * @param  string $line
	 * @return array
	 */
	public function getTranslationVariables($line)
	{
		preg_match_all('/:(\S\w*)/', $line, $matches);
		return (isset($matches[1])) ? $matches[1] : array();
	}

	/**
	 * Find line occurrence in given path
	 * @param  string $group
	 * @param  string $line
	 * @return intiger
	 */
	public function findLineCount($group, $line)
	{
		return  App::make('Finder')->files()->name('*.php')->in(app_path())->exclude($this->getIgnoredFolders())->contains("$group.$line")->count();
	}

	/**
	 * Load languages in custom array.
	 *
	 * If you know how to override the loaded array inside the Translator class let me know!
	 * @param  string $group
	 * @return array
	 */
	protected function loadGroup($group)
	{
		if($loaded = array_get($this->loaded, $this->language . "." . $group)) return $loaded;
		$lines = (Lang::has($group)) ? Lang::get($group) : array();
		array_set($this->loaded, $this->language . "." . $group, $lines);

		return $lines;
	}

	/**
	 * Write translations to file
	 * @param  string $group
	 * @param  array $lines
	 * @return boolean
	 */
	protected function writeToFile($group, $lines)
	{
		return file_put_contents($this->getFilePath($group), $this->arrayToString($lines));
	}

	/**
	 * Write array to pretty string format
	 * @param  array $lines
	 * @return string
	 */
	protected function arrayToString($lines)
	{
		$date    = Carbon::now()->format('d-m-Y H:i');
		$string  = "<?php # modified at $date \nreturn array(\n";
		$longest = $this->longestLine($lines);
		$spacing = str_repeat(" ", 10);
		ksort($lines);
		foreach($lines as $line => $translation){
			$spaces = (($diff = $longest - strlen($line)) > 0) ? str_repeat(" ", $diff) : '';
			$string .= "\t'$line'$spaces $spacing => $spacing '$translation',\n";
		}
		$string .= ");";
		return $string;
	}

	/**
	 * Return the longest translation
	 * @param  array $lines
	 * @return integer
	 */
	protected function longestLine($lines)
	{
		if(empty($lines)) return 0;
		return max(array_map('strlen', $lines)) + 2;
	}

	/**
	 * Return path to language group
	 * @param  string $group
	 * @return string
	 */
	protected function getFilePath($group)
	{
		return app_path('lang' . DIRECTORY_SEPARATOR . $this->language . DIRECTORY_SEPARATOR . $group . '.php');
	}

	/**
	 * Get path to current language
	 * @return string
	 */
	protected function getLanguagePath()
	{
		return app_path('lang' . DIRECTORY_SEPARATOR . $this->language);
	}

	/**
	 * Get files that need to be ignored
	 * @return array
	 */
	protected function getIgnoredFiles()
	{
		return Config::get('translate::search_exclude_files');
	}

	/**
	 * Get folders that need to be ignored
	 * @return array
	 */
	protected function getIgnoredFolders()
	{
		return Config::get('translate::search_ignore_folders');
	}

	/**
	 * Return all files within a language
	 * @return array
	 */
	public function getLanguageFiles()
	{
		$files   = array();
		$results = App::make('Finder')->files()->name('*.php')->in($this->getLanguagePath());
		$ignore  = $this->getIgnoredFiles();

		foreach($results as $result)
		{
			$group = $result->getBasename('.php');
			if(in_array($group, $ignore)) continue;
			array_set($files, $group, Lang::get($group));
		}

		return $files;
	}

}
