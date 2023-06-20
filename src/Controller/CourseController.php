<?php

namespace App\Controller;

use App\DTO\CourseResponseDTO;
use App\DTO\PaymentResponseDTO;
use App\Enum\CourseEnum;
use App\Repository\CourseRepository;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use PHP_CodeSniffer\Tokenizers\JS;

class CourseController extends AbstractController
{
    private ObjectManager $entityManager;
    private Serializer $serializer;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->serializer = SerializerBuilder::create()->build();
    }

    /**
     * @Route("/api/v1/courses", name="api_courses", methods={"GET"})
     * @OA\Get(
     *     path="/api/v1/courses",
     *     summary="Getting list of courses",
     *     description="Getting list of courses"
     * )
     * @OA\Response(
     *      response=200,
     *      description="The courses data",
     *      @OA\JsonContent(
     *          schema="CoursesInfo",
     *          type="array",
     *          @OA\Items(
     *              ref=@Model(
     *                  type=CourseResponseDTO::class,
     *                  groups={"info"}
     *              )
     *          )
     *      )
     * )
     * @OA\Tag(name="Course")
     */
    public function courses(CourseRepository $courseRepository)
    {
        $courses = $courseRepository->findAll();
        $response = [];
        foreach ($courses as $course) {
            $response[] = new CourseResponseDTO($course);
        }
        return new JsonResponse($response, Response::HTTP_OK);
    }

    /**
     * @Route("/api/v1/courses/{code}", name="api_course", methods={"GET"})
     * @OA\Get(
     *     path="/api/v1/courses/{code}",
     *     summary="Getting list of courses",
     *     description="Getting list of courses"
     * )
     * @OA\Parameter(
     *     name="code",
     *     in="path",
     *     description="Code courses (car-1 cooking-1 cleanCourse-1)",
     *     @OA\Schema(type="string")
     * )
     * @OA\Response(
     *      response=200,
     *      description="The course data",
     *      @OA\JsonContent(
     *          ref=@Model(
     *              type=CourseResponseDTO::class, groups={"info"}
     *          )
     *      )
     * ),
     * @OA\Response(
     *      response=404,
     *      description="Not found",
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(
     *              property="error",
     *              type="string"
     *          )
     *       )
     * )
     * @OA\Tag(name="Course")
     */
    public function course(string $code, CourseRepository $courseRepository)
    {
        $course = $courseRepository->findOneBy(['code' => $code]);
        if (!$course) {
            return new JsonResponse(['errors' => "Курс $code не найден"], Response::HTTP_NOT_FOUND);
        }
        $course = new CourseResponseDTO($course);
        return new JsonResponse($course, Response::HTTP_OK);
    }

    /**
     * @Route("/api/v1/courses/{code}/pay", name="api_pay_for_courses", methods={"POST"})
     * @OA\Post(
     *     path="/api/v1/courses/{code}/pay",
     *     summary="Pay courses",
     *     description="Pay courses"
     * )
     * @OA\Parameter(
     *     name="code",
     *     in="path",
     *     description="Code courses (car-1 cooking-1 cleanCourse-1)",
     *     @OA\Schema(type="string")
     * )
     * @OA\Response(
     *      response=200,
     *      description="Succeded pay info",
     *      @OA\JsonContent(
     *          schema="PayInfo",
     *          type="object",
     *          @OA\Property(
     *              property="success",
     *              type="boolean"
     *          ),
     *          @OA\Property(
     *              property="course_type",
     *              type="string"
     *          ),
     *          @OA\Property(
     *              property="expires_at",
     *              type="datetime",
     *              format="Y-m-d\\TH:i:sP"
     *          )
     *      )
     * ),
     * @OA\Response(
     *      response=406,
     *      description="Not enough funds",
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(
     *              property="error",
     *              type="string"
     *          )
     *      )
     * ),
     * @OA\Response(
     *      response=409,
     *      description="User has already paid for this course",
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(
     *              property="error",
     *              type="string"
     *          )
     *      )
     * )
     * @OA\Tag(name="Course")
     * @Security(name="Bearer")
     */
    public function payForCourses(string $code, PaymentService $paymentService, CourseRepository $courseRepository)
    {
        $course = $courseRepository->findOneBy(['code' => htmlspecialchars($code)]);
        if (!$course) {
            return new JsonResponse(['success' => false, 'errors' => "Курс $code не найден"], Response::HTTP_NOT_FOUND);
        }
        if ($course->getType() === CourseEnum::FREE) {
            $response = new PaymentResponseDTO(true, $course->getType(), null);
            return new JsonResponse($response, Response::HTTP_OK);
        }
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(
                ['success' => false, 'errors' => 'Пользователь не авторизован'],
                Response::HTTP_UNAUTHORIZED
            );
        }
        try {
            $transaction = $paymentService->payment($user, $course);
            $expires = $transaction->getExpires() ?: null;
            $response = new PaymentResponseDTO(true, $course->getType(), $expires);
            return new JsonResponse($response, Response::HTTP_OK);
        } catch (\RuntimeException $exeption) {
            return new JsonResponse(
                ['success' => false, 'errors' => $exeption->getMessage()],
                Response::HTTP_NOT_ACCEPTABLE
            );
        } catch (\LogicException $exeption) {
            return new JsonResponse(['success' => false, 'errors' => $exeption->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/course", name="app_course")
     */
    public function index(): Response
    {
        return $this->render('course/index.html.twig', [
            'controller_name' => 'CourseController',
        ]);
    }
}
