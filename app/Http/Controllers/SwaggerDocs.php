<?php

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="ONWYND API",
 *      description="API Documentation for ONWYND Mental Health Platform",
 *
 *      @OA\Contact(
 *          email="support@onwynd.com"
 *      ),
 *
 *      @OA\License(
 *          name="Apache 2.0",
 *          url="http://www.apache.org/licenses/LICENSE-2.0.html"
 *      )
 * )
 *
 * @OA\PathItem(
 *     path="/api/health",
 *
 *     @OA\Get(
 *         operationId="health",
 *         tags={"Health"},
 *         summary="Health check endpoint",
 *         description="Returns the application health status",
 *
 *         @OA\Response(
 *             response=200,
 *             description="Successful operation"
 *         )
 *     )
 * )
 */
