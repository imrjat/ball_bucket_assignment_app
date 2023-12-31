<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBallBucketAssignmentRequest;
use App\Models\Ball;
use App\Models\BallBucketAssignment;
use App\Models\Bucket;
use Illuminate\Http\RedirectResponse;

class BallBucketAssignmentController extends Controller
{
    public function __invoke(StoreBallBucketAssignmentRequest $request): RedirectResponse
    {
        // Validated input data from the request
        $data = $request->validated();

        // Retrieve all balls
        $balls = Ball::get()->keyBy('id');

        // Retrieve buckets with adjusted capacity
        $buckets = Bucket::withSum('assignments', 'occupied_capacity')->get();

        // Retrieve buckets with adjusted capacity
        $buckets = Bucket::withSum('assignments', 'occupied_capacity')
            ->get()
            ->each(function (Bucket $bucket) {
                $bucket->remaining_capacity = $bucket->capacity - ($bucket->assignments_sum_occupied_capacity ?? 0);
            })
            ->where('remaining_capacity', '>', 0)
            ->sortByDesc('capacity');

        // If no buckets can accommodate balls, redirect with error message
        if ($buckets->isEmpty()) {
            return redirect()->route('home')->with('error', 'All buckets are full');
        }

        $ballBucketAssignment = [];

        // Assign balls to buckets
        foreach ($data['ball_ids'] as $ball_id => $quantity) {
            if ($quantity > 0) {

                // Distribute the ball across multiple buckets
                foreach ($buckets as $bucket) {
                    $remainingCapacity = $bucket->remaining_capacity;

                    if ($remainingCapacity > 0) {
                        $ballsToAssign = min($quantity, floor($remainingCapacity / $balls[$ball_id]->size));
                        $requiredCapacity = $balls[$ball_id]->size * $ballsToAssign;

                        if ($ballsToAssign > 0) {
                            $ballBucketAssignment[] = [
                                'ball_id' => $ball_id,
                                'bucket_id' => $bucket->id,
                                'no_of_ball' => $ballsToAssign,
                                'occupied_capacity' => $requiredCapacity,
                            ];
                        }

                        $bucket->remaining_capacity -= $requiredCapacity;
                        $quantity -= $ballsToAssign;

                        if ($quantity <= 0) {
                            break; // All balls have been assigned
                        }
                    }
                }
            }
        }

        // Insert the assignment data into the database
        BallBucketAssignment::insert($ballBucketAssignment);

        return redirect()->route('home')->with('success', 'Bucket Assigned successfully');
    }
}
