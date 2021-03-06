<?php

namespace MarvinLabs\AcraServerBundle\Controller;

use Doctrine\ORM\Mapping\Entity;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

use MarvinLabs\AcraServerBundle\Entity\Crash;
use MarvinLabs\AcraServerBundle\DataFixtures\LoadFixtureData;

class CrashController extends Controller
{
	// TODO Disable in PROD environment
// 	public function generateTestDataAction()
// 	{	
//   		$doctrine = $this->getDoctrine()->getManager();
		
//   		$fixtureDataLoader = new LoadFixtureData();
//   		$fixtures = $fixtureDataLoader->load($doctrine);

//   		return new Response( var_dump($fixtures) );
// 	}
	
	/**
	 * Add a crash to the DB and send a notification to the crash admin
	 * 
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function addAction()
	{
        $request = $this->getRequest();
    	$crash = $this->newCrashFromRequest($request);

       	// Persist crash
  		$doctrine = $this->getDoctrine()->getManager();
		$doctrine->persist($crash);
   		$doctrine->flush();

        $protocol = $this->container->getParameter('use_https') === 'yes' ? 'https' : http;
        $sendNotification = $this->shouldNotifyForCrash($crash);

        if ($sendNotification) {
            // Send notification email
            $this->sendNewCrashNotification(
                    $this->get('mailer'),
                    $this->get('twig'),
                    $this->container->getParameter('notifications_from'),
                    $this->container->getParameter('notifications_to'),
                    $crash,
                    $protocol
                );
        }
   		
		return new Response( '' );
	}

    /**
     * Checks if a crash should trigger notification, based on config parameters
     *
     * @param \MarvinLabs\AcraServerBundle\Entity\Crash $crash
     * @return boolean
     */
    private function shouldNotifyForCrash(Crash $crash)
    {
        $notify = false;

        if ($this->container->getParameter('notify_on_crash') === 'yes') {
            $notify = true;
        }
        else if($this->container->getParameter('notify_on_new_issue') === 'yes') {
            $numCrashesForIssue = $this->getNumberOfCrashesForIssue($crash->getIssueId());

            if ($numCrashesForIssue === 1) {
                $notify = true;
            }
        }
        else if($this->container->getParameter('notify_on_first_crash_of_issue_daily') === 'yes') {
            $crashesToday = $this->getNumberOfCrashesForIssueToday($crash->getIssueId());

            if ($crashesToday === 1) {
                $notify = true;
            }
        }

        return $notify;
    }

    /**
     * Gets the number of crashes for an issueId
     *
     * @param string $issueId
     * @return int
     */
    private function getNumberOfCrashesForIssue($issueId) {
        $doctrine = $this->getDoctrine()->getManager();
        $crashRepo = $doctrine->getRepository('MLabsAcraServerBundle:Crash');
        $crashes = $crashRepo->newIssueCrashesQuery($issueId)->getResult();

        return count($crashes);
    }
    
    /**
     * Gets the number of crashes for an issueId today
     *
     * @param string $issueId
     * @return int
     */
    private function getNumberOfCrashesForIssueToday($issueId) {
        $doctrine = $this->getDoctrine()->getManager();
        $crashRepo = $doctrine->getRepository('MLabsAcraServerBundle:Crash');
        $crashes = $crashRepo->newIssueCrashesQuery($issueId)->getResult();

        $count = 0;
        $today = date('Y-m-d');

        foreach ($crashes as $crash) {
            $crashCreatedAt = $crash->getCreatedAt();
            if ($crashCreatedAt->format('Y-m-d') === $today) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Send an email notification about a new crash
     */
    private function sendNewCrashNotification($mailer, $twig, $from, $to, Crash $crash, $protocol)
    {
    	$message = \Swift_Message::newInstance()
	    	->setFrom($from)
	    	->setTo($to)
	    	->setSubject(sprintf(
	            	'[Acra Server] New crash for your application %s',
	    			$crash->getPackageName())
	    		)
	        ->setBody(
	            $twig
	    			->loadTemplate('MLabsAcraServerBundle:Notifications:crash_notification_body.html.twig')
	                ->render(array(
                        'crash' => $crash,
                        'protocol' => $protocol,
                        'numberOfCrashes' => $this->getNumberOfCrashesForIssue($crash->getIssueId()),
                        'numberOfCrashesToday' => $this->getNumberOfCrashesForIssueToday($crash->getIssueId()),
                        'hashBasedOn' => $crash->getNormalizedStacktrace(),
                    ))
	            );
	    		
    	$mailer->send($message);
    }
    
    /**
     * Build a crash from the parameters passed to the request
     * 
     * @return \MarvinLabs\AcraServerBundle\Entity\Crash
     */
    private function newCrashFromRequest($request)
    {
    	$requestData = $request->request;
    	
    	$crash = new Crash();
    	$crash->setAndroidVersion($requestData->get('ANDROID_VERSION', null));
    	$crash->setAppVersionCode($requestData->get('APP_VERSION_CODE', null));
    	$crash->setAppVersionName($requestData->get('APP_VERSION_NAME', null));
    	$crash->setApplicationLog($requestData->get('APPLICATION_LOG', null));
    	$crash->setAvailableMemSize($requestData->get('AVAILABLE_MEM_SIZE', null));
    	$crash->setBrand($requestData->get('BRAND', null));
    	$crash->setBuild($requestData->get('BUILD', null));
    	$crash->setCrashConfiguration($requestData->get('CRASH_CONFIGURATION', null));
    	$crash->setCustomData($requestData->get('CUSTOM_DATA', null));
    	$crash->setDeviceFeatures($requestData->get('DEVICE_FEATURES', null));
    	$crash->setDeviceId($requestData->get('DEVICE_ID', null));
    	$crash->setDisplay($requestData->get('DISPLAY', null));
    	$crash->setDropbox($requestData->get('DROPBOX', null));
    	$crash->setDumpsysMeminfo($requestData->get('DUMPSYS_MEMINFO', null));
    	$crash->setEnvironment($requestData->get('ENVIRONMENT', null));
    	$crash->setEventsLog($requestData->get('EVENTSLOG', null));
    	$crash->setFilePath($requestData->get('FILE_PATH', null));
    	$crash->setInitialConfiguration($requestData->get('INITIAL_CONFIGURATION', null));
    	$crash->setInstallationId($requestData->get('INSTALLATION_ID', null));
    	$crash->setIsSilent($requestData->get('IS_SILENT', null));
    	$crash->setLogcat($requestData->get('LOGCAT', null));
    	$crash->setMediaCodecList($requestData->get('MEDIA_CODEC_LIST', null));
    	$crash->setPackageName($requestData->get('PACKAGE_NAME', null));
    	$crash->setPhoneModel($requestData->get('PHONE_MODEL', null));
    	$crash->setProduct($requestData->get('PRODUCT', null));
    	$crash->setRadioLog($requestData->get('RADIOLOG', null));
    	$crash->setReportId($requestData->get('REPORT_ID', null));
    	$crash->setSettingsGlobal($requestData->get('SETTINGS_GLOBAL', null));
    	$crash->setSettingsSecure($requestData->get('SETTINGS_SECURE', null));
    	$crash->setSettingsSystem($requestData->get('SETTINGS_SYSTEM', null));
    	$crash->setSharedPreferences($requestData->get('SHARED_PREFERENCES', null));
    	$crash->setStackTrace($requestData->get('STACK_TRACE', null));
    	$crash->setThreadDetails($requestData->get('THREAD_DETAILS', null));
    	$crash->setTotalMemSize($requestData->get('TOTAL_MEM_SIZE', null));
    	$crash->setUserComment($requestData->get('USER_COMMENT', null));
    	$crash->setUserEmail($requestData->get('USER_EMAIL', null));
    	
    	$tmpDateTime = new \DateTime( $requestData->get('USER_APP_START_DATE', null) );
        $tmpDateTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    	$crash->setUserAppStartDate($tmpDateTime);

    	$tmpDateTime = new \DateTime( $requestData->get('USER_CRASH_DATE', null) );
        $tmpDateTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    	$crash->setUserCrashDate($tmpDateTime);
    	
    	return $crash;
    }
}
