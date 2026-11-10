<?php

namespace App\Shared\Observabilidade;

use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessorBuilder;
use OpenTelemetry\SDK\Trace\TracerProvider;

/**
 * SDK OpenTelemetry PHP (OIK-ADR-0010 §9). Desligado por padrão (`OTEL_SDK_DISABLED=true` no dev e nos
 * testes): o `trace_id` dos logs vem então do `traceparent` recebido ou é gerado. Ligado, a requisição
 * vira um span filho do `traceparent`, exportado por OTLP/HTTP ao coletor (Grafana Alloy).
 */
final class Rastreamento
{
    private ?TracerProviderInterface $provedor = null;

    public function ligado(): bool
    {
        return (bool) config('oikora.otel.ligado');
    }

    public function tracer(): TracerInterface
    {
        return $this->provedor()->getTracer('oikora-api', (string) config('oikora.versao'));
    }

    /** @param  array<string, string>  $cabecalhos  cabeçalhos W3C recebidos (`traceparent`, `tracestate`) */
    public function iniciarRequisicao(string $nome, array $cabecalhos): ?SpanInterface
    {
        if (! $this->ligado() || $nome === '') {
            return null;
        }

        $pai = TraceContextPropagator::getInstance()->extract($cabecalhos);

        return $this->tracer()->spanBuilder($nome)
            ->setParent($pai)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();
    }

    public function encerrar(): void
    {
        if ($this->provedor instanceof TracerProvider) {
            $this->provedor->shutdown();
            $this->provedor = null;
        }
    }

    private function provedor(): TracerProviderInterface
    {
        if ($this->provedor !== null) {
            return $this->provedor;
        }

        if (! $this->ligado()) {
            return $this->provedor = new NoopTracerProvider;
        }

        $transporte = (new OtlpHttpTransportFactory)->create(
            rtrim((string) config('oikora.otel.endpoint'), '/').'/v1/traces',
            'application/x-protobuf',
        );

        $recurso = ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create([
            'service.name' => 'oikora-api',
            'service.version' => (string) config('oikora.versao'),
            'deployment.environment' => (string) config('app.env'),
        ])));

        return $this->provedor = TracerProvider::builder()
            ->addSpanProcessor((new BatchSpanProcessorBuilder(new SpanExporter($transporte)))->build())
            ->setResource($recurso)
            ->build();
    }
}
