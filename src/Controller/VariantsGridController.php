<?php

declare(strict_types=1);

namespace Webflare\ViewsTheme\Controller;

use Webflare\ViewsTheme\Service\VariantsGridLoader;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class VariantsGridController extends StorefrontController
{
    private const DEFAULT_ROWS_TEMPLATE = '@ViewsTheme/components/VariantsGrid/Rows.html.twig';
    private const DEFAULT_PAGINATION_TEMPLATE = '@ViewsTheme/components/VariantsGrid/Pagination.html.twig';

    public function __construct(
        private readonly CartService $cartService,
        private readonly LineItemFactoryRegistry $lineItemFactoryRegistry,
        private readonly VariantsGridLoader $variantsGridLoader,
        private readonly Environment $twig,
    ) {}

    #[Route(path: '/checkout/variants-grid/add', name: 'frontend.checkout.variants-grid.add', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function addVariants(Cart $cart, RequestDataBag $requestDataBag, Request $request, SalesChannelContext $context): Response
    {
        $lineItems = $requestDataBag->get('lineItems');
        if (!$lineItems instanceof RequestDataBag) {
            throw RoutingException::missingRequestParameter('lineItems');
        }

        $items = [];
        $count = 0;

        /** @var RequestDataBag $lineItemData */
        foreach ($lineItems as $lineItemData) {
            $data = $lineItemData->all();
            $quantity = isset($data['quantity']) ? (int) $data['quantity'] : 0;

            if ($quantity <= 0) {
                continue;
            }

            $item = $this->lineItemFactoryRegistry->create($this->getLineItemArray($data), $context);
            $count += $item->getQuantity();
            $items[] = $item;
        }

        if ($items === []) {
            $this->addFlash(self::DANGER, $this->trans('variantsGrid.error.noSelection'));

            return $this->createActionResponse($request);
        }

        $cart = $this->cartService->add($cart, $items, $context);

        if (!$this->traceErrors($cart)) {
            $this->addFlash(self::SUCCESS, $this->trans('checkout.addToCartSuccess', ['%count%' => $count]));
        }

        return $this->createActionResponse($request);
    }

    #[Route(path: '/checkout/variants-grid/load', name: 'frontend.checkout.variants-grid.load', defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function loadPage(Request $request, SalesChannelContext $context): JsonResponse
    {
        $parentId = (string) $request->query->get('parentId');

        if ($parentId === '') {
            throw RoutingException::missingRequestParameter('parentId');
        }

        $variantsGrid = $this->variantsGridLoader->load($parentId, $request, $context);

        if ($variantsGrid === null) {
            return new JsonResponse(['rows' => '', 'pagination' => '', 'page' => 1, 'total' => 0]);
        }

        $pagination = $variantsGrid->getPagination();
        $selectedOptionIds = $variantsGrid->getSelectedOptionIds();

        $rowsTemplate = $this->resolveTemplate(
            $request->query->get('rowsTemplate'),
            self::DEFAULT_ROWS_TEMPLATE,
        );
        $paginationTemplate = $this->resolveTemplate(
            $request->query->get('paginationTemplate'),
            self::DEFAULT_PAGINATION_TEMPLATE,
        );

        if ($rowsTemplate === self::DEFAULT_ROWS_TEMPLATE) {
            $rowsHtml = $this->twig->createTemplate('{{- component(name, props) -}}')->render([
                'name' => 'ViewsTheme:VariantsGrid:Rows',
                'props' => [
                    'variants' => $variantsGrid->getVariants(),
                    'groups' => $variantsGrid->getGroups(),
                ],
            ]);
        } else {
            $rowsHtml = $this->renderView($rowsTemplate, [
                'variants' => $variantsGrid->getVariants(),
                'groups' => $variantsGrid->getGroups(),
            ]);
        }

        $paginationProps = [
            'pagination' => $pagination,
            'pageParameter' => $variantsGrid->getPageParameter(),
            'query' => $selectedOptionIds === []
                ? []
                : [$variantsGrid->getOptionsParameter() => implode('|', $selectedOptionIds)],
        ];

        // The default template is an anonymous UX component (`{% props %}`), so it must render as a component
        if ($paginationTemplate === self::DEFAULT_PAGINATION_TEMPLATE) {
            $paginationHtml = $this->twig->createTemplate('{{- component(name, props) -}}')->render([
                'name' => 'ViewsTheme:VariantsGrid:Pagination',
                'props' => $paginationProps,
            ]);
        } else {
            $paginationHtml = $this->renderView($paginationTemplate, $paginationProps);
        }

        return new JsonResponse([
            'rows' => $rowsHtml,
            'pagination' => $paginationHtml,
            'page' => $pagination->getPage(),
            'total' => $pagination->getTotal(),
        ]);
    }

    /**
     * @param array<string|int, mixed> $data
     *
     * @return array<string|int, mixed>
     */
    private function getLineItemArray(array $data): array
    {
        if (isset($data['quantity'])) {
            $data['quantity'] = (int) $data['quantity'];
        }

        if (isset($data['stackable'])) {
            $data['stackable'] = (bool) $data['stackable'];
        }

        if (isset($data['removable'])) {
            $data['removable'] = (bool) $data['removable'];
        }

        if (isset($data['priceDefinition']['quantity'])) {
            $data['priceDefinition']['quantity'] = (int) $data['priceDefinition']['quantity'];
        }

        if (isset($data['priceDefinition']['isCalculated'])) {
            $data['priceDefinition']['isCalculated'] = (int) $data['priceDefinition']['isCalculated'];
        }

        return $data;
    }

    private function traceErrors(Cart $cart): bool
    {
        if ($cart->getErrors()->count() <= 0) {
            return false;
        }

        $this->addCartErrors($cart, fn(Error $error) => $error->isPersistent());

        return true;
    }

    private function resolveTemplate(mixed $template, string $default): string
    {
        if (!is_string($template) || $template === '') {
            return $default;
        }

        if (!preg_match('/^@[A-Za-z0-9_]+\/[A-Za-z0-9_\-\/\.]+\.html\.twig$/', $template)) {
            return $default;
        }

        if (str_contains($template, '..')) {
            return $default;
        }

        if (!$this->twig->getLoader()->exists($template)) {
            return $default;
        }

        return $template;
    }
}
