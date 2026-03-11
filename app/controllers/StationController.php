<?php
/**
 * StationController - Handle station setup request operations
 */

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\StationRequest;
use App\Models\User;
use App\Helpers\ValidationHelper;

class StationController extends BaseController
{
    private $stationModel;
    private $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->stationModel = new StationRequest();
        $this->userModel = new User();
    }

    /**
     * Display station requests
     */
    public function index()
    {
        $status = $this->query('status') ?? 'all';
        $page = intval($this->query('page') ?? 1);

        $message = $this->getMessage();

        $where = [];
        if ($status !== 'all') {
            $where['status'] = ucfirst($status);
        }

        $paginated = $this->stationModel->paginate($page, ITEMS_PER_PAGE, $where, 'request_date DESC');
        $stations = $paginated['items'];

        // Get stats
        $stats = $this->stationModel->getStatistics();

        $this->data = [
            'user' => $this->user,
            'stations' => $stations,
            'message' => $message,
            'stats' => $stats,
            'status' => $status,
            'page' => $page,
            'pagination' => $paginated,
        ];

        $this->render('stations/index', $this->data);
    }

    /**
     * Display station detail
     */
    public function show()
    {
        $id = intval($this->query('id'));

        if (!$id) {
            $this->error('Invalid station ID');
            $this->redirect(APP_URL . '/stations.php');
        }

        $station = $this->stationModel->find($id);

        if (!$station) {
            $this->error('Station not found');
            $this->redirect(APP_URL . '/stations.php');
        }

        $requester = $this->userModel->find($station['requested_by']);
        $assigned = $station['assigned_to'] ? $this->userModel->find($station['assigned_to']) : null;
        $approver = $station['approved_by'] ? $this->userModel->find($station['approved_by']) : null;

        $this->data = [
            'user' => $this->user,
            'station' => $station,
            'requester' => $requester,
            'assigned' => $assigned,
            'approver' => $approver,
        ];

        $this->render('stations/show', $this->data);
    }

    /**
     * Display request station form
     */
    public function request()
    {
        if (!$this->hasPermission(CAN_REQUEST_STATION)) {
            $this->error('You do not have permission to request stations');
            $this->redirect(APP_URL . '/stations.php');
        }

        $this->data = [
            'user' => $this->user,
            'csrf_token' => $this->getCsrfToken(),
        ];

        $this->render('stations/request', $this->data);
    }

    /**
     * Handle request station submission
     */
    public function requestStore()
    {
        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/stations/request.php');
        }

        if (!$this->hasPermission(CAN_REQUEST_STATION)) {
            $this->error('You do not have permission');
            $this->redirect(APP_URL . '/stations.php');
        }

        // Verify CSRF
        if (!$this->verifyCsrfToken($this->post('csrf_token'))) {
            $this->error('Invalid security token');
            $this->redirect(APP_URL . '/stations/request.php');
        }

        // Validate
        $required = ['station_name', 'location', 'estimated_cost'];
        if (!$this->validateRequired($required, $_POST)) {
            $this->error('All required fields must be filled');
            $this->redirect(APP_URL . '/stations/request.php');
        }

        // Validate cost
        $cost = floatval($this->post('estimated_cost'));
        if (!ValidationHelper::numeric($cost, 0)) {
            $this->error('Estimated cost must be a positive number');
            $this->redirect(APP_URL . '/stations/request.php');
        }

        // Validate coordinates
        $lat = $this->post('latitude');
        $lon = $this->post('longitude');
        if ($lat && $lon && !ValidationHelper::coordinates(floatval($lat), floatval($lon))) {
            $this->error('Invalid coordinates');
            $this->redirect(APP_URL . '/stations/request.php');
        }

        // Create request
        $id = $this->stationModel->create([
            'station_name' => $this->sanitize($this->post('station_name')),
            'location' => $this->sanitize($this->post('location')),
            'latitude' => $lat ? floatval($lat) : null,
            'longitude' => $lon ? floatval($lon) : null,
            'description' => $this->sanitize($this->post('description') ?? ''),
            'requested_by' => $this->user['id'],
            'request_date' => date(DATETIME_FORMAT),
            'estimated_cost' => $cost,
            'status' => STATION_STATUS_PENDING,
        ]);

        if ($id) {
            $this->logActivity('REQUEST', 'Requested station setup', 'station_requests', $id);
            $this->success('Station request submitted successfully');
            $this->redirect(APP_URL . '/stations.php?id=' . $id);
        }

        $this->error('Failed to submit station request');
        $this->redirect(APP_URL . '/stations/request.php');
    }

    /**
     * Approve station request
     */
    public function approve()
    {
        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/stations.php');
        }

        if (!$this->hasPermission(CAN_APPROVE_STATIONS)) {
            $this->error('You do not have permission to approve stations');
            $this->redirect(APP_URL . '/stations.php');
        }

        $id = intval($this->post('id'));
        $assignedTo = intval($this->post('assigned_to') ?? 0);

        if (!$id) {
            $this->error('Invalid station ID');
            $this->redirect(APP_URL . '/stations.php');
        }

        if ($this->stationModel->approve($id, $this->user['id'], $assignedTo)) {
            $this->logActivity('APPROVE', 'Approved station request', 'station_requests', $id);
            $this->success('Station request approved');
            $this->redirect(APP_URL . '/stations.php?id=' . $id);
        }

        $this->error('Failed to approve station request');
        $this->redirect(APP_URL . '/stations.php?id=' . $id);
    }

    /**
     * Assign station to technician
     */
    public function assign()
    {
        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/stations.php');
        }

        if (!$this->hasPermission(CAN_APPROVE_STATIONS)) {
            $this->error('You do not have permission');
            $this->redirect(APP_URL . '/stations.php');
        }

        $id = intval($this->post('id'));
        $technicianId = intval($this->post('technician_id'));

        if (!$id || !$technicianId) {
            $this->error('Invalid request');
            $this->redirect(APP_URL . '/stations.php');
        }

        if ($this->stationModel->assignTo($id, $technicianId)) {
            $this->logActivity('ASSIGN', 'Assigned station to technician', 'station_requests', $id);
            $this->success('Station assigned successfully');
            $this->redirect(APP_URL . '/stations.php?id=' . $id);
        }

        $this->error('Failed to assign station');
        $this->redirect(APP_URL . '/stations.php?id=' . $id);
    }

    /**
     * Mark station as completed
     */
    public function complete()
    {
        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/stations.php');
        }

        $id = intval($this->post('id'));
        $notes = $this->sanitize($this->post('notes') ?? '');

        if (!$id) {
            $this->error('Invalid station ID');
            $this->redirect(APP_URL . '/stations.php');
        }

        $station = $this->stationModel->find($id);

        // Only assigned technician or manager can complete
        if ($station['assigned_to'] !== $this->user['id'] && 
            !$this->hasPermission(['Super Admin', 'Manager'])) {
            $this->error('You do not have permission');
            $this->redirect(APP_URL . '/stations.php');
        }

        if ($this->stationModel->markCompleted($id, $notes)) {
            $this->logActivity('COMPLETE', 'Completed station setup', 'station_requests', $id);
            $this->success('Station marked as completed');
            $this->redirect(APP_URL . '/stations.php?id=' . $id);
        }

        $this->error('Failed to mark station as completed');
        $this->redirect(APP_URL . '/stations.php?id=' . $id);
    }

    /**
     * Reject station request
     */
    public function reject()
    {
        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/stations.php');
        }

        if (!$this->hasPermission(CAN_APPROVE_STATIONS)) {
            $this->error('You do not have permission');
            $this->redirect(APP_URL . '/stations.php');
        }

        $id = intval($this->post('id'));
        $reason = $this->sanitize($this->post('reason') ?? '');

        if (!$id) {
            $this->error('Invalid station ID');
            $this->redirect(APP_URL . '/stations.php');
        }

        if ($this->stationModel->reject($id, $reason)) {
            $this->logActivity('REJECT', 'Rejected station request', 'station_requests', $id);
            $this->success('Station request rejected');
            $this->redirect(APP_URL . '/stations.php?id=' . $id);
        }

        $this->error('Failed to reject station request');
        $this->redirect(APP_URL . '/stations.php?id=' . $id);
    }

    /**
     * Get my assigned stations
     */
    public function myStations()
    {
        $stations = $this->stationModel->getAssignedTo($this->user['id']);

        $this->data = [
            'user' => $this->user,
            'stations' => $stations,
        ];

        $this->render('stations/my_stations', $this->data);
    }

    /**
     * Get station statistics
     */
    public function stats()
    {
        $stats = $this->stationModel->getStatistics();
        $this->json(['success' => true, 'data' => $stats]);
    }
}
