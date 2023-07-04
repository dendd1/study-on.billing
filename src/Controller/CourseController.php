<?php

namespace App\Controller;

use App\DTO\CourseRequestDTO;
use App\DTO\CourseResponseDTO;
use App\DTO\PaymentResponseDTO;
use App\Entity\Course;
use App\Enum\CourseEnum;
use App\Repository\CourseRepository;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerBuilder;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use PHP_CodeSniffer\Tokenizers\JS;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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
     * @Route("/api/v1/courses/new", name="api_new_course", methods={"POST"})
     * @OA\Post(
     *     path="/api/v1/courses/new",
     *     summary="Create new course",
     *     description="Create new course"
     * )
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="name",
     *          type="string",
     *          description="name course",
     *          example="new name test course",
     *        ),
     *        @OA\Property(
     *          property="type",
     *          type="smallint",
     *          description="type course",
     *          example="1",
     *        ),
     *        @OA\Property(
     *          property="price",
     *          type="float",
     *          description="price course",
     *          example="100",
     *        ),
     *        @OA\Property(
     *          property="code",
     *          type="string",
     *          description="code course",
     *          example="test-code-1",
     *        ),
     *     )
     *  )
     * @OA\Response(
     *      response=200,
     *      description="Succeded create",
     *      @OA\JsonContent(
     *          schema="CoursesInfo",
     *          type="object",
     *          @OA\Property(property="success", type="boolean"),
     *      )
     * ),
     * @OA\Response(
     *      response=401,
     *      description="UNAUTHORIZED",
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(property="error", type="string")
     *      )
     * ),
     * @OA\Response(
     *      response=400,
     *      description="Name cannot be empty",
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(property="error", type="string")
     *      )
     * ),
     * @OA\Response(
     *      response=403,
     *      description="Course must have the price",
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(property="error", type="string")
     *      )
     * ),
     * @OA\Response(
     *      response=409,
     *      description="Course with this code is alredy exist",
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(property="error", type="string")
     *      )
     * )
     * @OA\Tag(name="Course")
     * @Security(name="Bearer")
     */
    public function new(
        Request $request,
        JWTTokenManagerInterface $jwtManager,
        TokenStorageInterface $tokenStorageInterface,
        CourseRepository $courseRepository
    ) {

        if (!$tokenStorageInterface->getToken()) {
            return new JsonResponse(['errors' => 'Нет токена'], Response::HTTP_UNAUTHORIZED);
        }
        if (!$this->getUser() || !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles(), true)) {
            return new JsonResponse(['errors' => 'Отказ в доступе'], Response::HTTP_UNAUTHORIZED);
        }
        $course = $this->serializer->deserialize($request->getContent(), CourseRequestDTO::class, 'json');
        if ($course->getName() == null) {
            return new JsonResponse(['errors' => 'Название не может быть пустым'], Response::HTTP_BAD_REQUEST);
        }
        if ($course->getType() == CourseEnum::FREE) {
            $course->setPrice(null);
        } else {
            if ($course->getPrice() == null) {
                return new JsonResponse(['errors' => 'Курс платный, укажите цену'], Response::HTTP_FORBIDDEN);
            }
        }
        if ($courseRepository->count(['code' => $course->getCode()]) > 0) {
            return new JsonResponse(['errors' => 'Курс с таким кодом уже существует'], Response::HTTP_CONFLICT);
        }
        $courseRepository->add(Course::fromDTO($course), true);
        return new JsonResponse(['success' => true], Response::HTTP_CREATED);
    }

    /**
     * @Route("/api/v1/courses/{code}/edit", name="api_edit_course", methods={"POST"})
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="name",
     *          type="string",
     *          description="name course",
     *          example="new name of editing test course",
     *        ),
     *        @OA\Property(
     *          property="type",
     *          type="smallint",
     *          description="type course",
     *          example="1",
     *        ),
     *        @OA\Property(
     *          property="price",
     *          type="float",
     *          description="price course",
     *          example="100",
     *        ),
     *        @OA\Property(
     *          property="code",
     *          type="string",
     *          description="code course",
     *          example="test-edit-code-1",
     *        ),
     *     )
     *  )
     * @OA\Post(
     *     path="/api/v1/courses/{code}/edit",
     *     summary="Edit course",
     *     description="Edit course"
     * )
     * @OA\Response(
     *      response=200,
     *      description="Succeded pay info",
     *      @OA\JsonContent(
     *          schema="PayInfo",
     *          type="object",
     *          @OA\Property(property="success", type="boolean"),
     *      )
     * ),
     * @OA\Response(
     *      response=401,
     *      description="UNAUTHORIZED",
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(property="error", type="string")
     *      )
     * ),
     * @OA\Response(
     *      response=404,
     *      description="Course with that code not found",
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(property="error", type="string")
     *      )
     * ),
     * @OA\Response(
     *      response=406,
     *      description="Not enough funds",
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(property="error", type="string")
     *      )
     * ),
     * @OA\Response(
     *      response=409,
     *      description="User has already paid for this course",
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(property="error", type="string")
     *      )
     * )
     * @OA\Tag(name="Course")
     * @Security(name="Bearer")
     */
    public function edit(
        string $code,
        Request $request,
        JWTTokenManagerInterface $jwtManager,
        TokenStorageInterface $tokenStorageInterface,
        CourseRepository $courseRepository
    ) {
        if (!$tokenStorageInterface->getToken() || !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles(), true)) {
            return new JsonResponse(['errors' => 'Нет токена'], Response::HTTP_UNAUTHORIZED);
        }
        if (!$this->getUser()) {
            return new JsonResponse(['errors' => 'Пользователь не авторизован'], Response::HTTP_UNAUTHORIZED);
        }
        $course = $this->serializer->deserialize($request->getContent(), CourseRequestDTO::class, 'json');
        if ($course->getName() == null) {
            return new JsonResponse(['errors' => 'Название не может быть пустым'], Response::HTTP_BAD_REQUEST);
        }
        if ($course->getType() == CourseEnum::FREE) {
            $course->setPrice(null);
        } else {
            if ($course->getPrice() == null) {
                return new JsonResponse(['errors' => 'Курс платный, укажите цену'], Response::HTTP_FORBIDDEN);
            }
        }
        $edited_course = $courseRepository->findOneBy(['code' => $code]);
        if ($edited_course == null) {
            return new JsonResponse(['errors' => 'Курс с таким кодом не существует'], Response::HTTP_CONFLICT);
        }
        $courseRepository->add($edited_course->fromDTOedit($course), true);
        return new JsonResponse(['success' => true], Response::HTTP_OK);
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
