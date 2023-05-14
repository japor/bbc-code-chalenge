<?php

namespace Drupal\bbc_courses\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ChangedCommand;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\bbc_courses\StudentRepository;
use Drupal\Core\Url;

/**
 * Class Course Signup Form.
 */
class CourseSignupForm extends FormBase {

  /**
   * The student repository.
   *
   * @var \Drupal\bbc_courses\StudentRepository
   */
  protected $studentRepository;

  /**
   * The student data.
   *
   * @var array|null
   */
  protected $student;

  /**
   * CourseSignupForm constructor.
   *
   * @param \Drupal\bbc_courses\StudentRepository $studentRepository
   *   The student repository.
   */
  public function __construct(StudentRepository $studentRepository) {
    $this->studentRepository = $studentRepository;
    $this->student = $this->studentRepository->getStudent();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('bbc_courses.student_repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'course_signup_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $courses = $this->studentRepository->getCourses();
    $this->student = $this->studentRepository->getStudent();
    
    // Username field
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => isset($this->student['username']) ? $this->student['username'] : '',
      '#required' => TRUE,
      '#element_validate' => ['::validateAlphanumeric'],
      '#prefix' => '<div class="signup-form-wrapper">',
    ];

    // Email field
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => isset($this->student['email']) ? $this->student['email'] : '',
      '#required' => TRUE,
    ];

    // Student ID field
    $form['student_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Student ID'),
      '#required' => TRUE,
      '#default_value' => isset($this->student['student_id']) ? $this->student['student_id'] : '',
      '#element_validate' => ['::validateNumeric'],
    ];

    // Course Subject select field
    $form['course_subject'] = [
      '#type' => 'select',
      '#title' => $this->t('Course Subject'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#options' => $courses,
      '#ajax' => [
        'callback' => '::updateCourseTopic',
        'wrapper' => 'course-topic-wrapper',
      ],
    ];

    // Course Topic select field
    $form['course_topic'] = [
      '#type' => 'select',
      '#title' => $this->t('Course Topic'),
      '#required' => TRUE,
      '#validated' => TRUE,
      // '#disabled' => TRUE,
      '#prefix' => '<div id="course-topic-wrapper">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => '::updateTopicTimeslot',
        'wrapper' => 'course-topic-timeslot-wrapper',
      ],
      '#states' => [
        'disabled' => [
          '[name="course_subject"]' => [
            'value' => '',
          ],
        ],
      ],
    ];

    // Course Timeslot select field
    $form['course_timeslot'] = [
      '#type' => 'select',
      '#title' => $this->t('Course Timeslot'),
      '#required' => TRUE,
      '#validated' => TRUE,
      // '#disabled' => TRUE,
      '#prefix' => '<div id="course-topic-timeslot-wrapper">',
      '#suffix' => '</div>',
       '#states' => [
        'disabled' => [
          '[name="course_topic"]' => [
            'value' => '',
          ],
        ],
      ],
    ];

    // Add Course submit button
    $form['add_course'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Course'),
      '#suffix' => '</div>',
    ];

    $form['signup_preview'] = array(
      '#type' => 'fieldset',
      '#title' => 'Preview',
      '#attributes' => [
        'class' => [
          'signup-preview-wrapper',
        ],
      ],
    );
    $form['signup_preview']['username'] = array(
      '#type' => 'label',
      '#title' => isset($this->student['username']) ? $this->student['username'] : '',
    );
    $form['signup_preview']['email'] = array(
      '#type' => 'label',
      '#title' => isset($this->student['email']) ? $this->student['email'] : '',
    );
    $form['signup_preview']['student_id'] = array(
      '#type' => 'label',
      '#title' => isset($this->student['student_id']) ? $this->student['student_id'] : '',
    );

    $form['signup_preview']['courses'] = array(
      '#type' => 'table',
      '#caption' => $this->t('Courses'),
      '#header' => array(
        $this->t('Subject'),
        $this->t('Topic'),
        $this->t('Schedule'),
      ),
    );

    if (isset($this->student['courses'])) {
      // sort the earliest to latest.
      usort($this->student['courses'], fn($a, $b) => strtotime($a['timeslot']) - strtotime($b['timeslot']));

      foreach($this->student['courses'] as $course) {
        $form['signup_preview']['courses']['#rows'][] = [
          $course['subject'],
          $course['topic'],
          $course['timeslot'],
        ];
      }
    }

    // Add Course submit button
    $form['signup_preview']['delete_data'] = [
      '#type' => 'button',
      '#value' => $this->t('Delete Data'),
      '#ajax' => [
        'callback' => '::deleteData',
        'event' => 'click',
      ],
      '#limit_validation_errors' => [],
    ];

    // Disable Caching.
    $form['#cache']['max-age'] = 0;
    // Attach bootsrap cdn.
    $form['#attached']['library'][] = 'bbc_courses/custom';

    return $form;
  }

  /**
 * Validates that a form element contains only alphanumeric characters.
 *
 * @param array $element
 *   The form element to validate.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current state of the form.
 */
public function validateAlphanumeric($element, FormStateInterface $form_state) {
  $value = $form_state->getValue($element['#parents']);
  if (!preg_match('/^[a-zA-Z0-9]+$/', $value)) {
    $form_state->setError($element, $this->t('The %label field should contain only alphanumeric characters.', ['%label' => $element['#title']]));
  }
}

/**
 * Validates that a form element contains only numeric characters.
 *
 * @param array $element
 *   The form element to validate.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current state of the form.
 */
public function validateNumeric($element, FormStateInterface $form_state) {
  $value = $form_state->getValue($element['#parents']);
  if (!is_numeric($value)) {
    $form_state->setError($element, $this->t('The %label field should contain only numeric characters.', ['%label' => $element['#title']]));
  }
}

/**
 * Updates the options for the course topic select element based on the selected course subject.
 *
 * @param array $form
 *   The form array.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current state of the form.
 *
 * @return array
 *   The updated form element for the course topic.
 */
public function updateCourseTopic(array &$form, FormStateInterface $form_state) {
  $course_subject = $form_state->getValue('course_subject');
  $form['course_topic']['#options'] = $this->studentRepository->getTopics($course_subject);

  return $form['course_topic'];
}

/**
 * Updates the options for the course timeslot select element based on the selected course subject and topic.
 *
 * @param array $form
 *   The form array.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current state of the form.
 *
 * @return array
 *   The updated form element for the course timeslot.
 */
public function updateTopicTimeslot(array &$form, FormStateInterface $form_state) {
  $course_subject = $form_state->getValue('course_subject');
  $course_topic = $form_state->getValue('course_topic');

  $form['course_timeslot']['#options'] = $this->studentRepository->getTimeslots($course_subject, $course_topic);

  return $form['course_timeslot'];
}

/**
 * Deletes the student data.
 *
 * @return \Drupal\Core\Ajax\AjaxResponse
 *   The Ajax response.
 */
public function deleteData() {
  $this->studentRepository->deleteStudent();
  $response = new AjaxResponse();
  $currentURL = Url::fromRoute('<current>');
  $response->addCommand(new RedirectCommand($currentURL->toString()));

  return $response;
}

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $course_timeslot = $form_state->getValue('course_timeslot');
    
    if (isset($this->student['courses'])) {
      foreach($this->student['courses'] as $course ) {
        // Has conflicted schedule.
        if ($course_timeslot === $course['timeslot']) {
          $form_state->setErrorByName ('course_timeslot', $this->t('%subject %topic %timeslot has conflict with your schedule.', [
          '%subject' => $course['subject'],
          '%topic' => $course['topic'],
          '%timeslot' => $course['timeslot'],
        ]));    
        }
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $student = $this->studentRepository->getStudent();

    // Prepare updated data.
    $update_data = [
      'username' => $form_state->getValue('username'),
      'email' => $form_state->getValue('email'),
      'student_id' => $form_state->getValue('student_id'),
    ];
    if($student) {
      // Append the previous courses.
      $update_data['courses'] = $student['courses'];  
    }

    // Add the new course.
    $update_data['courses'][] = [
      'subject' => $form_state->getValue('course_subject'),
      'topic' => $form_state->getValue('course_topic'),
      'timeslot' => $form_state->getValue('course_timeslot'),
    ];

    $this->studentRepository->createStudent($update_data);
    
    \Drupal::messenger()->addMessage('Successfully added');
  }

}
