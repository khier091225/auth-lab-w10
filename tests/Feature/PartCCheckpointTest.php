<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartCCheckpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_access(): void
    {
        $response = $this->get('/courses');
        $response->assertStatus(200);

        // Ana's active course (course 1)
        $course1 = Course::where('is_active', true)->first();
        $this->get("/courses/{$course1->id}")->assertStatus(200);

        // Inactive course
        $inactive = Course::where('is_active', false)->first();
        $this->get("/courses/{$inactive->id}")->assertStatus(403);

        // Auth protected
        $this->get('/courses/create')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_student_carla_authorization(): void
    {
        $carla = User::where('email', 'carla@example.com')->first();
        $ben = User::where('email', 'ben@example.com')->first();
        $bensCourse = Course::where('user_id', $ben->id)->first();
        $course1 = Course::where('is_active', true)->first();

        $this->actingAs($carla)->get('/courses/create')->assertStatus(403);
        $this->actingAs($carla)->get("/courses/{$bensCourse->id}/edit")->assertStatus(403);
        $this->actingAs($carla)->get('/admin')->assertStatus(403);

        $show = $this->actingAs($carla)->get("/courses/{$course1->id}");
        $show->assertStatus(200);
        $show->assertDontSee('Edit');
        $show->assertDontSee('Delete');
    }

    public function test_instructor_ana_authorization(): void
    {
        $ana = User::where('email', 'ana@example.com')->first();
        $ben = User::where('email', 'ben@example.com')->first();
        $anaActiveWithStudent = Course::where('user_id', $ana->id)->has('students')->first();
        $anaCourseNoStudent = Course::where('user_id', $ana->id)->doesntHave('students')->where('is_active', true)->first();
        $anaInactive = Course::where('user_id', $ana->id)->where('is_active', false)->first();
        $bensCourse = Course::where('user_id', $ben->id)->first();

        // Ana creates a course
        $createRes = $this->actingAs($ana)->post('/courses', [
            'code' => 'CS999',
            'title' => 'New Test Course',
            'units' => 3,
            'is_active' => 1,
        ]);
        $newCourse = Course::where('code', 'CS999')->first();
        $this->assertNotNull($newCourse);
        $this->assertEquals($ana->id, $newCourse->user_id);
        $createRes->assertRedirect(route('courses.show', $newCourse));

        // Can edit own active and inactive courses
        $this->actingAs($ana)->get("/courses/{$anaActiveWithStudent->id}/edit")->assertStatus(200);
        $this->actingAs($ana)->get("/courses/{$anaInactive->id}")->assertStatus(200);

        // Cannot edit Ben's course
        $this->actingAs($ana)->get("/courses/{$bensCourse->id}/edit")->assertStatus(403);

        // Ana views course with student: shows edit link and cannot delete message
        $showCourse1 = $this->actingAs($ana)->get("/courses/{$anaActiveWithStudent->id}");
        $showCourse1->assertSee('Edit');
        $showCourse1->assertSee('Cannot delete: students are enrolled');

        // Ana cannot delete course with student enrolled (403)
        $this->actingAs($ana)->delete("/courses/{$anaActiveWithStudent->id}")->assertStatus(403);

        // Ana can delete course without enrolled students (302)
        $this->actingAs($ana)->delete("/courses/{$anaCourseNoStudent->id}")->assertRedirect('/courses');
        $this->assertDatabaseMissing('courses', ['id' => $anaCourseNoStudent->id]);

        // Ben cannot view Ana's inactive course
        $this->actingAs($ben)->get("/courses/{$anaInactive->id}")->assertStatus(403);
    }

    public function test_admin_authorization(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $ben = User::where('email', 'ben@example.com')->first();
        $bensCourse = Course::where('user_id', $ben->id)->first();
        $courseWithStudent = Course::has('students')->first();

        $this->actingAs($admin)->get('/admin')->assertStatus(200);
        $this->actingAs($admin)->get("/courses/{$bensCourse->id}/edit")->assertStatus(200);

        // Admin can delete any course, even with students
        $this->actingAs($admin)->delete("/courses/{$courseWithStudent->id}")->assertRedirect('/courses');
        $this->assertDatabaseMissing('courses', ['id' => $courseWithStudent->id]);
    }

    public function test_new_registration_defaults_to_student(): void
    {
        $this->post('/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $newUser = User::where('email', 'newuser@example.com')->first();
        $this->assertEquals('student', $newUser->role);

        $this->actingAs($newUser)->get('/admin')->assertStatus(403);
    }
}
