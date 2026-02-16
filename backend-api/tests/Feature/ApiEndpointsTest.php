<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MentorProfile;
use App\Models\MentorshipSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: User Registration (Mentor)
     */
    public function test_can_register_as_mentor(): void
    {
        $response = $this->postJson('/api/register', [
            'name'     => 'John Mentor',
            'email'    => 'john.mentor@test.com',
            'password' => 'password123',
            'role'     => 'mentor',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ])
            ->assertJson([
                'message' => 'User registered successfully',
                'user' => [
                    'name'  => 'John Mentor',
                    'email' => 'john.mentor@test.com',
                    'role'  => 'mentor',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john.mentor@test.com',
            'role'  => 'mentor',
        ]);
    }

    /**
     * Test 2: User Registration (Mentee)
     */
    public function test_can_register_as_mentee(): void
    {
        $response = $this->postJson('/api/register', [
            'name'     => 'Jane Mentee',
            'email'    => 'jane.mentee@test.com',
            'password' => 'password123',
            'role'     => 'mentee',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'User registered successfully',
                'user' => [
                    'name'  => 'Jane Mentee',
                    'email' => 'jane.mentee@test.com',
                    'role'  => 'mentee',
                ],
            ]);
    }

    /**
     * Test 3: Registration Validation - Missing Fields
     */
    public function test_registration_fails_with_missing_fields(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    /**
     * Test 4: Registration Validation - Invalid Role
     */
    public function test_registration_fails_with_invalid_role(): void
    {
        $response = $this->postJson('/api/register', [
            'name'     => 'Test User',
            'email'    => 'test@test.com',
            'password' => 'password123',
            'role'     => 'invalid_role',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    /**
     * Test 5: Registration Validation - Duplicate Email
     */
    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@test.com']);

        $response = $this->postJson('/api/register', [
            'name'     => 'Test User',
            'email'    => 'existing@test.com',
            'password' => 'password123',
            'role'     => 'mentee',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test 6: User Login - Success
     */
    public function test_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email'    => 'login@test.com',
            'password' => bcrypt('password123'),
            'role'     => 'mentee',
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'login@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email'],
                'token',
            ])
            ->assertJson([
                'message' => 'Login successful',
            ]);
    }

    /**
     * Test 7: User Login - Invalid Credentials
     */
    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email'    => 'login@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'login@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test 8: Get Mentors - Authenticated
     */
    public function test_can_get_mentors_when_authenticated(): void
    {
        $user = User::factory()->create(['role' => 'mentee']);
        
        // Create a mentor with profile
        $mentor = User::factory()->create(['role' => 'mentor']);
        MentorProfile::create([
            'user_id'   => $mentor->id,
            'bio'       => 'Experienced software developer',
            'skills'    => ['PHP', 'Laravel', 'JavaScript'],
            'strengths' => ['Problem solving', 'Communication'],
            'weaknesses'=> ['Time management'],
            'verified'  => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/mentors');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'mentors' => [
                    '*' => ['id', 'user_id', 'bio', 'skills', 'user']
                ]
            ]);
    }

    /**
     * Test 9: Get Mentors - Unauthenticated
     */
    public function test_cannot_get_mentors_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/mentors');

        $response->assertStatus(401);
    }

    /**
     * Test 10: Create Session - Success
     */
    public function test_can_create_mentorship_session(): void
    {
        $mentor = User::factory()->create(['role' => 'mentor']);
        $mentee = User::factory()->create(['role' => 'mentee']);

        $response = $this->actingAs($mentee, 'sanctum')
            ->postJson('/api/sessions', [
                'mentor_id'    => $mentor->id,
                'scheduled_at' => '2026-02-10 10:00:00',
                'duration'     => 60,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'session' => ['id', 'mentor_id', 'mentee_id', 'scheduled_at', 'duration', 'status']
            ])
            ->assertJson([
                'message' => 'Session booked successfully',
                'session' => [
                    'mentor_id' => $mentor->id,
                    'mentee_id' => $mentee->id,
                    'status'    => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('mentorship_sessions', [
            'mentor_id' => $mentor->id,
            'mentee_id' => $mentee->id,
        ]);
    }

    /**
     * Test 11: Create Session - Validation Errors
     */
    public function test_session_creation_fails_with_invalid_data(): void
    {
        $mentee = User::factory()->create(['role' => 'mentee']);

        $response = $this->actingAs($mentee, 'sanctum')
            ->postJson('/api/sessions', [
                'mentor_id'    => 9999, // Non-existent mentor
                'scheduled_at' => 'invalid-date',
                'duration'     => 10, // Less than minimum 30
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mentor_id', 'scheduled_at', 'duration']);
    }

    /**
     * Test 12: Create Session - Unauthenticated
     */
    public function test_cannot_create_session_when_unauthenticated(): void
    {
        $response = $this->postJson('/api/sessions', [
            'mentor_id'    => 1,
            'scheduled_at' => '2026-02-10 10:00:00',
            'duration'     => 60,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test 13: Create Payment - Success
     */
    public function test_can_create_payment(): void
    {
        $mentor = User::factory()->create(['role' => 'mentor']);
        $mentee = User::factory()->create(['role' => 'mentee']);

        $session = MentorshipSession::create([
            'mentor_id'    => $mentor->id,
            'mentee_id'    => $mentee->id,
            'scheduled_at' => '2026-02-10 10:00:00',
            'duration'     => 60,
            'status'       => 'pending',
        ]);

        $response = $this->actingAs($mentee, 'sanctum')
            ->postJson('/api/payments', [
                'session_id' => $session->id,
                'amount'     => 100.00,
                'method'     => 'credit_card',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'payment' => ['id', 'session_id', 'payer_id', 'amount', 'method', 'status']
            ])
            ->assertJson([
                'message' => 'Payment recorded successfully',
                'payment' => [
                    'session_id' => $session->id,
                    'payer_id'   => $mentee->id,
                    'status'     => 'completed',
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'session_id' => $session->id,
            'payer_id'   => $mentee->id,
        ]);
    }

    /**
     * Test 14: Create Payment - Validation Errors
     */
    public function test_payment_creation_fails_with_invalid_data(): void
    {
        $mentee = User::factory()->create(['role' => 'mentee']);

        $response = $this->actingAs($mentee, 'sanctum')
            ->postJson('/api/payments', [
                'session_id' => 9999, // Non-existent session
                'amount'     => -50,  // Negative amount
                'method'     => '',   // Empty method
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session_id', 'amount', 'method']);
    }

    /**
     * Test 15: Create Payment - Unauthenticated
     */
    public function test_cannot_create_payment_when_unauthenticated(): void
    {
        $response = $this->postJson('/api/payments', [
            'session_id' => 1,
            'amount'     => 100.00,
            'method'     => 'credit_card',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test 16: Create Rating - Success
     */
    public function test_can_create_rating(): void
    {
        $mentor = User::factory()->create(['role' => 'mentor']);
        $mentee = User::factory()->create(['role' => 'mentee']);

        $response = $this->actingAs($mentee, 'sanctum')
            ->postJson('/api/ratings', [
                'mentor_id' => $mentor->id,
                'rating'    => 5,
                'comment'   => 'Excellent mentor! Very helpful and knowledgeable.',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'rating' => ['id', 'mentor_id', 'mentee_id', 'rating', 'comment']
            ])
            ->assertJson([
                'message' => 'Rating submitted successfully',
                'rating' => [
                    'mentor_id' => $mentor->id,
                    'mentee_id' => $mentee->id,
                    'rating'    => 5,
                ],
            ]);

        $this->assertDatabaseHas('ratings', [
            'mentor_id' => $mentor->id,
            'mentee_id' => $mentee->id,
            'rating'    => 5,
        ]);
    }

    /**
     * Test 17: Create Rating - Without Comment
     */
    public function test_can_create_rating_without_comment(): void
    {
        $mentor = User::factory()->create(['role' => 'mentor']);
        $mentee = User::factory()->create(['role' => 'mentee']);

        $response = $this->actingAs($mentee, 'sanctum')
            ->postJson('/api/ratings', [
                'mentor_id' => $mentor->id,
                'rating'    => 4,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Rating submitted successfully',
            ]);
    }

    /**
     * Test 18: Create Rating - Validation Errors
     */
    public function test_rating_creation_fails_with_invalid_data(): void
    {
        $mentee = User::factory()->create(['role' => 'mentee']);

        $response = $this->actingAs($mentee, 'sanctum')
            ->postJson('/api/ratings', [
                'mentor_id' => 9999, // Non-existent mentor
                'rating'    => 10,   // Out of range (max 5)
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mentor_id', 'rating']);
    }

    /**
     * Test 19: Create Rating - Unauthenticated
     */
    public function test_cannot_create_rating_when_unauthenticated(): void
    {
        $response = $this->postJson('/api/ratings', [
            'mentor_id' => 1,
            'rating'    => 5,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test 20: Logout - Success
     */
    public function test_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);

        // Verify tokens are deleted
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Test 21: Logout - Unauthenticated
     */
    public function test_cannot_logout_when_unauthenticated(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }
}
