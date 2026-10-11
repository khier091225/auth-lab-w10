<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartBFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_can_browse_courses_and_sees_guest_navigation(): void
    {
        $response = $this->get('/courses');
        $response->assertStatus(200);
        $response->assertSee('Log in');
        $response->assertSee('Register');

        $activeCourse = Course::where('is_active', true)->first();
        $detail = $this->get("/courses/{$activeCourse->id}");
        $detail->assertStatus(200);
        $detail->assertSee($activeCourse->title);
    }

    public function test_guest_cannot_access_course_management_routes(): void
    {
        $activeCourse = Course::where('is_active', true)->first();

        $this->get('/courses/create')->assertRedirect('/login');
        $this->post('/courses', [
            'code' => 'CS101',
            'title' => 'Intro',
            'units' => 3,
        ])->assertRedirect('/login');
        $this->get("/courses/{$activeCourse->id}/edit")->assertRedirect('/login');
        $this->put("/courses/{$activeCourse->id}", [
            'code' => 'CS101',
            'title' => 'Intro Updated',
            'units' => 3,
        ])->assertRedirect('/login');
        $this->delete("/courses/{$activeCourse->id}")->assertRedirect('/login');
    }

    public function test_authenticated_user_navigation_displays_user_and_role(): void
    {
        $carla = User::where('email', 'carla@example.com')->first();
        $response = $this->actingAs($carla)->get('/courses');
        $response->assertStatus(200);
        $response->assertSee('Carla Student (student)');
    }

    public function test_course_creation_assigns_authenticated_user_as_owner(): void
    {
        $ana = User::where('email', 'ana@example.com')->first();

        $response = $this->actingAs($ana)->post('/courses', [
            'code' => 'IT401',
            'title' => 'Advanced Web Dev',
            'units' => 4,
            'is_active' => 1,
        ]);

        $course = Course::where('code', 'IT401')->first();
        $this->assertNotNull($course);
        $this->assertEquals($ana->id, $course->user_id);
        $response->assertRedirect(route('courses.show', $course));
    }

    public function test_database_seeder_establishes_required_entities_and_relations(): void
    {
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com', 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['email' => 'ana@example.com', 'role' => 'instructor']);
        $this->assertDatabaseHas('users', ['email' => 'ben@example.com', 'role' => 'instructor']);
        $this->assertDatabaseHas('users', ['email' => 'carla@example.com', 'role' => 'student']);

        $ana = User::where('email', 'ana@example.com')->first();
        $carla = User::where('email', 'carla@example.com')->first();

        $this->assertCount(3, $ana->courses);
        $this->assertEquals(1, $carla->enrolledCourses()->count());
    }
}
