<?php
namespace Drupal\bbc_courses;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class StudentRepository.
 */
class StudentRepository {

  /**
   * The session manager.
   *
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  protected $session;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new StudentRepository object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * 
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   *   The session manager.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler, SessionInterface $session) {
    $this->session = $session;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('session')
    );
  }

  /**
   * Get Data.
   *
   * @return array
   *   An array of Courses data.
   */
  public function getData() {
    // Path of json file.
    $json_file_path = $this->moduleHandler->getModule('bbc_courses')->getPath().'/data/courses.json';
    // Get the json data.
    $data = json_decode(file_get_contents($json_file_path), true);
    return $data;
  }

  /**
   * Get Courses List.
   *
   * @return array
   *   An array of Courses List.
   */
  public function getcourses() {
    $data = $this->getData();
    $courses = [];
    foreach($data as $key => $value) {
      $courses[$key] = $key;
    }
    return $courses;
  }

  /**
   * Get Topics List.
   * 
   * @param string $course_subject
   *   An string of course subject.
   *
   * @return array
   *   An array of Topics List.
   */
  public function getTopics($course_subject) {
    $data = $this->getData();
    $topics = [''=>'- Select -'];
    foreach($data[$course_subject] as $key => $value) {
      $topics[$key] = $key;
    }
    return $topics;
  }

  /**
   * Get Timeslot List.
   * 
   * @param string $course_subject
   *   An string of course subject.
   * 
   * @param string $course_topic
   *   An string of course topic.
   *
   * @return array
   *   An array of Timeslot List.
   */
  public function getTimeslots($course_subject, $course_topic) {
    $data = $this->getData();
    $timeslot = [''=>'- Select -'];
    foreach($data[$course_subject][$course_topic]['timeslot'] as $value) {
      $timeslot[$value] = $value;
    }
    return $timeslot;
  }

  /**
   * Get Student.
   *
   * @return array
   *   An array of student data.
   */
  public function getStudent() {
    $student = $this->session->get('student', []);
    return $student;
  }

  /**
   * Create a new student.
   *
   * @param array $student
   *   An array of student data.
   *
   * @return string
   *   The Data of newly created student.
   */
  public function createStudent(array $student) {
    $this->session->set('student', $student);
    return $student;
  }

  /**
   * Add course to student data.
   * 
   * @param array $course
   *   An array of course.
   * 
   * @return bool
   *   TRUE if the student is successfully added, FALSE otherwise.
   */
  public function addCourse($course) {
    $student = $this->getStudent();
    $student['courses'][] = $course;
    $this->session->set('student', $student);
    return $student;
  }

  /**
   * Delete a student.
   *
   * @return bool
   *   TRUE if the student is successfully deleted, FALSE otherwise.
   */
  public function deleteStudent() {
    $this->session->remove('student');
    return $this->session->isStarted() ? $this->session->save() : TRUE;
  }

}
