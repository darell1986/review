<?php
class WebsiteForm extends CFormModel {
	public $domain;
	public $ip;

	public function rules() {
		return array(
			array('domain', 'required'),
			array('domain', 'filter', 'filter'=>array($this, 'trimDomain')),
			array('domain', 'match', 'pattern' => '#^[a-z\d-]{1,62}\.[a-z\d-]{1,62}(.[a-z\d-]{1,62})*$#i'),
			array('domain', 'isReachable'),
			array('domain', 'tryToAnalyse'),
		);
	}

	public function attributeLabels() {
		return array(
			'domain' => Yii::t("app", "Domain"),
		);
	}

	public function trimDomain($domain) {
		if(!$this -> hasErrors()) {
			$domain=trim($domain);
			$domain=trim($domain, "/");
			$domain=strtolower($domain);
			$domain = preg_replace("(https?://)", "", $domain);
			return $domain;
		}
	}

	public function isReachable() {
		if(!$this -> hasErrors()) {
			$this -> ip = gethostbyname($this -> domain);
			$long = ip2long($this -> ip);
			if($long == -1 OR $long === FALSE) {
				$this->addError("domain", Yii::t("app", "Could not reach host: {Host}", array("{Host}" => $this -> domain)));
			}
		}
	}

	public function tryToAnalyse() {
		if(!$this -> hasErrors()) {
			//Remove "www" from domain
			$this -> domain = str_replace("www.", '', $this -> domain);

			// Get command instance
			$command = Yii::app() -> db -> createCommand();

			// Check if website already exists in the database
			$website = $command -> select('modified, id') -> from('{{website}}') -> where('md5domain=:id', array(':id'=>md5($this -> domain))) -> queryRow();

			// If website exists and we do not need to update data then exit from method
			if($website AND ($notUpd = (strtotime($website['modified']) + Yii::app() -> params["analyser"]["cacheTime"] > time()))) {
				return true;
			} elseif($website AND !$notUpd) {
				Utils::deletePdf($this->domain);
                Utils::deletePdf($this->domain."_pagespeed");
				$args = array('yiic', 'parse', 'update', "--domain={$this -> domain}", "--ip={$this -> ip}", "--wid={$website['id']}");
			} else {
				$args = array('yiic', 'parse', 'insert', "--domain={$this -> domain}", "--ip={$this -> ip}");
			}

			// Get command path
			$commandPath = Yii::app() -> getBasePath() . DIRECTORY_SEPARATOR . 'commands';

			// Create new console command runner
			$runner = new CConsoleCommandRunner();

			// Adding commands
			$runner -> addCommands($commandPath);

			// If something goes wrong return error
			if($error = $runner -> run ($args)) {
				$this -> addError("domain", Yii::t("app", "Error Code $error"));
			} else {
				return true;
			}
		}
	}

}