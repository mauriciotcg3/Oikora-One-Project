ARG GO_VERSAO=1.24
FROM golang:${GO_VERSAO}-alpine AS compilador

ARG MINIO_VERSAO
RUN apk add --no-cache bash git
RUN git clone --branch "${MINIO_VERSAO}" --depth 1 https://github.com/minio/minio.git /src/minio
WORKDIR /src/minio
RUN LDFLAGS="$(go run buildscripts/gen-ldflags.go)" \
    && CGO_ENABLED=0 go build -tags kqueue -trimpath --ldflags "${LDFLAGS}" -o /minio .

FROM alpine:3.22
RUN apk add --no-cache ca-certificates su-exec tzdata \
    && addgroup -S minio \
    && adduser -S -G minio minio
COPY --from=compilador /minio /usr/local/bin/minio
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod 0755 /usr/local/bin/docker-entrypoint.sh
VOLUME ["/data"]
EXPOSE 9000 9001
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
