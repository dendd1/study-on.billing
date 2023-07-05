<?php

namespace App\Controller;

use App\DTO\TransactionResponseDTO;
use App\Entity\Course;
use App\Entity\Transaction;
use App\Enum\TransactionEnum;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use PHP_CodeSniffer\Tokenizers\JS;

class TransactionsController extends AbstractController
{
    private ObjectManager $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/api/v1/transactions", name="api_get_transactions", methods={"GET"})
     * @OA\Get(
     *     path="/api/v1/transactions",
     *     summary="Pay courses",
     *     description="Pay courses"
     * )
     * @OA\Parameter(
     *     name="type",
     *     in="query",
     *     description="Transaction type filter",
     *     @OA\Property(type="string")
     * ),
     * @OA\Parameter(
     *     name="code",
     *     in="query",
     *     description="Course code filter",
     *     @OA\Property(type="string")
     * ),
     * @OA\Parameter(
     *     name="skip_expired",
     *     in="query",
     *     description="Skip expired transactions filter (e.g. rent payment)",
     *     @OA\Property(type="boolean")
     * ),
     * @OA\Response(
     *      response=200,
     *      description="The transactions data",
     *      @OA\JsonContent(
     *          schema="TransactionsInfo",
     *          type="array",
     *          @OA\Items(
     *              ref=@Model(
     *                  type=TransactionResponseDTO::class,
     *                  groups={"info"}
     *              )
     *          )
     *      )
     * )
     * @OA\Tag(name="Transactions")
     * @Security(name="Bearer")
     * @throws JWTDecodeFailureException
     */
    public function transactions(
        Request                  $request,
        JWTTokenManagerInterface $jwtManager,
        TokenStorageInterface    $tokenStorageInterface
    ): JsonResponse {
        $token = $tokenStorageInterface->getToken();
        if (null === $token) {
            return new JsonResponse(['message' => 'Нет токена'], Response::HTTP_UNAUTHORIZED);
        }
        $decodedJwtToken = $jwtManager->decode($token);
        $type = $request->query->get('type') ? TransactionEnum::TYPE_CODES[$request->query->get('type')] : null;
        $code = $request->query->get('code') ? : null;
        $skip_expired = $request->query->get('skip_expired');
        $course = $this->entityManager->getRepository(Course::class)->findOneBy(['code' => $code]);
        $criteria = Criteria::create()->where(Criteria::expr()->eq('customer', $this->getUser()));
        if ($type !== null) {
            $criteria->andWhere(Criteria::expr()->eq('type', $type));
        }
        if ($code) {
            $criteria->andWhere(Criteria::expr()->eq('course', $course));
        }
        if ($skip_expired) {
            $criteria->andWhere(Criteria::expr()->orX(Criteria::expr()
                ->gte('expires', new \DateTimeImmutable()), Criteria::expr()->isNull('expires')));
        }
        $transactions = $this->entityManager->getRepository(Transaction::class)->matching($criteria);
        $response = [];
        foreach ($transactions as $transaction) {
            $response[] = new TransactionResponseDTO($transaction);
        }
        return new JsonResponse($response, Response::HTTP_OK);
    }

    /**
     * @Route("/transactions", name="app_transactions")
     */
    public function index(): Response
    {
        return $this->render('transactions/index.html.twig', [
            'controller_name' => 'TransactionsController',
        ]);
    }
}
