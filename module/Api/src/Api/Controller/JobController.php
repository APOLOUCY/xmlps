<?php
namespace Api\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Xmlps\Logger\Logger;
use Zend\Mvc\I18n\Translator;
use Zend\Authentication\AuthenticationService;
use Manager\Entity\Job;
use Manager\Model\DAO\JobDAO;
use Manager\Model\DAO\DocumentDAO;
use Manager\Model\Queue\Manager as QueueManager;
use CitationstyleConversion\Model\Citationstyles;

class JobController extends AbstractApiController {

    protected $jobDAO;
    protected $documentDAO;
    protected $queueManager;
    protected $citationStyles;

    /**
     * Constructor
     *
     * @param Logger $logger
     * @param Translator $translator
     * @param AuthenticationService $authService
     * @param JobDAO $jobDAO
     * @param DocumentDAO $documentDAO
     * @param QueueManager $queueManager
     * @param CitationStyles $citationStyles
     *
     * @return void
     */
    public function __construct(
        Logger $logger,
        Translator $translator,
        AuthenticationService $authService,
        JobDAO $jobDAO,
        DocumentDAO $documentDAO,
        QueueManager $queueManager,
        CitationStyles $citationStyles
    )
    {
        parent::__construct($logger, $translator, $authService);

        $this->jobDAO = $jobDAO;
        $this->documentDAO = $documentDAO;
        $this->queueManager = $queueManager;
        $this->citationStyles = $citationStyles;
    }

    /**
     * Submit a new job
     *
     * NOTE: the submitted file content needs to be base64_encoded.
     * json_en/decode fails in certain cases otherwise
     *
     * @return array Array with submission status information
     */
    public function submitAction()
    {
        // Make sure the file name parameter is provided
        if (!($fileName = $this->params()->fromQuery('fileName'))) {
            return array(
                'error' => $this->translator->translate('job.api.error.fileNameParameterMissing')
            );
        }

        // Make sure the file content parameter is provided
        if (!($fileContent = $this->params()->fromQuery('fileContent'))) {
            return array(
                'error' => $this->translator->translate('job.api.error.fileContentParameterMissing')
            );
        }

        // Make sure the citation style parameter is provided
        if (!($citationStyleHash = $this->params()->fromQuery('citationStyleHash'))) {
            return array(
                'error' => $this->translator->translate('job.api.error.citationStyleHashParameterMissing')
            );
        }

        $citationStyles = $this->citationStyles->getStyleMap();
        if (!array_key_exists($citationStyleHash, $citationStyles)) {
            return array(
                'error' => $this->translator->translate('job.api.error.invalidCitationStyleHash')
            );
        }

        // Create a new job instance
        $job = $this->jobDAO->getInstance();
        $job->user = $this->identity();
        $job->setCitationStyleFileByHash($citationStyleHash);
        $this->jobDAO->save($job);

        // Create the inputFile
        $fileName = str_replace('/', '', $fileName);
        $fileName = $job->getDocumentPath() . '/' . $fileName;
        file_put_contents($fileName, base64_decode($fileContent));

        // Create new document
        $document = $this->documentDAO->getInstance();
        $document->job = $job;
        $document->conversionStage = $job->conversionStage;
        $document->path = $fileName;
        $this->documentDAO->save($document);

        $this->logger->infoTranslate('manager.job.createLog', $job->id);

        // Send the job to the queue manager
        $this->queueManager->addJob($job->id);

        return array('status' => 'success', 'id' => $job->id);
    }

    /**
     * Retrieve list of accepted citation styles and their hashes
     *
     * @return array Array containing citaion style list
     */
    public function citationStyleListAction()
    {
        $citationStyles = $this->citationStyles->getStyleMap();
        array_walk($citationStyles, function(&$v) { $v = $v['title']; });

        return array(
            'status' => 'success',
            'citationStyles' => $citationStyles,
        );
    }

    /**
     * Get the job status
     *
     * @return array Array containing job status information
     */
    public function statusAction()
    {
        $job = $this->getJob();
        if (!($job instanceof Job)) return $job;

        $jobStatusMap = $job->getStatusMap();
        return array(
            'status' => 'success',
            'jobStatus' => $job->status,
            'jobStatusDescription' => $jobStatusMap[$job->status]
        );
    }

    /**
     * Retrieve a completed job
     *
     * NOTE: the returned file content is base64_encoded
     *
     * @return array Array containing converted document
     */
    public function retrieveAction()
    {
        $job = $this->getJob();
        if (!($job instanceof Job)) return $job;

        if ($job->status != JOB_STATUS_COMPLETED) {
            return array(
                'error' => $this->translator->translate('job.api.error.jobNotCompleted')
            );
        }

        // Get the requested conversion stage
        if (!($conversionStage = (int) $this->params()->fromQuery('conversionStage'))) {
            return array(
                'error' => $this->translator->translate('job.api.error.conversionStageParameterMissing')
            );
        }

        // Get the document for the conversion stage
        if (!($document = $job->getStageDocument($conversionStage))) {
            return array(
                'error' => $this->translator->translate('job.api.error.documentNotFound')
            );
        }

        if (file_exists($document->path)) {
            return array(
                'fileName' => preg_replace('#^.*/#', '', $document->path),
                'fileContents' => base64_encode(file_get_contents($document->path)),
                'status' => 'success',
            );
        }
    }

    /**
     * Get a job instance for a given job id
     *
     * @return mixed Job instance or array with error messages
     */
    protected function getJob()
    {
        // Make sure the job id parameter is provided
        if (!($jobId = (int) $this->params()->fromQuery('id'))) {
            return array(
                'error' => $this->translator->translate('job.api.error.jobIdParameterMissing')
            );
        }

        $job = $this->jobDAO->find($jobId);

        // Check if the job exists and the user is the owner of the job
        if (!$job or !($this->identity()->id == $job->user->id)) {
            return array(
                'error' => $this->translator->translate('job.api.error.invalidJobId')
            );
        }

        return $job;
    }
}