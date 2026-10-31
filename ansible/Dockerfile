FROM alpine:3.20
RUN apk add --no-cache \
  ansible \
  openssh-client \
  sshpass \
  python3 \
  py3-pip

WORKDIR /ansible

RUN mkdir -p /root/.ssh && chmod 700 /root/.ssh

CMD ["ansible-playbook", "--version"]
