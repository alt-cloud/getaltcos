# Установка REDHAT openshift в ALTCOS

## Реализация

- Установка в среду PVE или LIBVIRT QEMU
- При установке развернуть для два диска - / (ext4)  и /var (btrfs). 
  Описание см [Creating a separate /var partition](https://docs.openshift.com/container-platform/4.9/installing/installing_platform_agnostic/installing-platform-agnostic.html#installation-user-infra-machines-advanced_vardisk_installing-platform-agnostic)

## Список виртуальных машин

Машина    | Количестао | Дистрибутив | Тип образа |vCPU | Virtual RAM | Storage | IOPS | Примечание
----------|------------|-------------|------------|-----|-------------|---------|------|-----------
Bootstrap |  1         | RHCOS       | ISO        |   4 |  16GB       |  100GB  | 300 | После разворачивания не нужен
Control plane (Master) | 3 | ALTCOS  | QCOW2      | 4   |  16GB       |  100GB  | 300
Compute (Worker)       | 2 | ALTCOS  | QCOW2      |   2 |  8GB       |  100GB  | 300

- Один виртуальный ЦП эквивалентен одному физическому ядру, если одновременная многопоточность (SMT) или гиперпоточность не включена. Если этот параметр включен, используйте следующую формулу для расчета соответствующего соотношения: (количество потоков на ядро × ядра) × сокеты = виртуальные ЦП.

- Платформа контейнеров OpenShift и Kubernetes чувствительны к производительности диска, поэтому рекомендуется более быстрое хранилище, особенно для etcd на узлах плоскости управления, для которых требуется продолжительность fsync p99 10 мс. Обратите внимание, что на многих облачных платформах размер хранилища и количество операций ввода-вывода в секунду масштабируются вместе, поэтому вам может потребоваться перераспределить объем хранилища, чтобы получить достаточную производительность. 

## Настройка DNS

Добавление файлов описания зоны в `/var/lib/bind/etc/local.conf`:
```
include "/etc/bind/rfc1912.conf";

zone "altlinux.io" {
  type master;
  file "master/altlinux.io";
};

zone "5.4.10.in-addr.arpa" {
  type master;
  file "5.4.10.in-addr.arpa";
};
```

Определение прямой зоны  в файле `/var/lib/bind/zone/master/altlinux.io` для поддоменов
- api.osp4;
- *.apps.osp4;
- bootstrap.ocp4;
- master0.osp4;
- master1.osp4;
- master2.osp4;
- worker0.osp4;
- worker1.osp4.
```
$TTL 14400
altlinux.io.   IN      SOA   ns1.altlinux.io. root.altlinux.io. (
        2022030102      ; Serial
        10800           ; Refresh
        3600            ; Retry
        604800          ; Expire
        604800          ; Negative Cache TTL
);
                        IN      NS      ns1
@                       IN      A       10.4.5.30

ns1     IN      A 10.4.5.30
api.ocp4 IN CNAME ns1 
*.apps.ocp4 IN CNAME ns1

bootstrap.ocp4 IN  A 10.4.5.31
master0.ocp4   IN       A 10.4.5.32
master1.ocp4   IN       A 10.4.5.33
master2.ocp4   IN       A 10.4.5.34

worker0.ocp4   IN  A 10.4.5.35
worker1.ocp4   IN  A 10.4.5.36
```

Определение обратной зоны для поддоменов в файле `/var/lib/bind/zone/5.4.10.in-addr.arpa`:
```
$TTL 3600
@       IN      SOA     ns1.altlinux.io. root.altlinux.io. (
        2022030101      ; Serial
        21600   ; refresh
        3600    ; retry
        3600000 ; expire
        86400 ) ; minimum

        IN      NS      ns1.altlinux.io.

30.5.4.10.in-addr.arpa. IN PTR api.ocp4.altlinux.io.
30.5.4.10.in-addr.arpa. IN PTR api-int.ocp4.altlinux.io.

31.5.4.10.in-addr.arpa. IN PTR bootstrap.ocp4.altlinux.io.
32.5.4.10.in-addr.arpa. IN PTR master0.ocp4.altlinux.io.
33.5.4.10.in-addr.arpa. IN PTR master1.ocp4.altlinux.io.
34.5.4.10.in-addr.arpa. IN PTR master2.ocp4.altlinux.io.

35.5.4.10.in-addr.arpa. IN PTR worker0.ocp4.altlinux.io.
36.5.4.10.in-addr.arpa. IN PTR worker1.ocp4.altlinux.io.
~                                                           
```

## Настройка балансировщика нагрузки

```
# /etc/haproxy/haproxy.cfg
#---------------------------------------------------------------------
# Global settings
#---------------------------------------------------------------------
global
    log /dev/log local0
    log /dev/log local1 notice
    daemon

#---------------------------------------------------------------------
# common defaults that all the 'listen' and 'backend' sections will
# use if not designated in their block
#---------------------------------------------------------------------
defaults
    mode                    http
    log                     global
    option                  httplog
    option                  dontlognull
    option http-server-close
    option forwardfor       except 127.0.0.0/8
    option                  redispatch
    retries                 1
    timeout http-request    10s
    timeout queue           20s
    timeout connect         5s
    timeout client          20s
    timeout server          20s
    timeout http-keep-alive 10s
    timeout check           100s
frontend api-server-6443
  bind *:6443
  mode tcp
  option tcplog
  default_backend api-server-6443

backend api-server-6443
  mode tcp
  #balance roundrobin
  server bootstrap bootstrap.ocp4.altlinux.io:6443 check 
  server master0 master0.ocp4.altlinux.io:6443 check inter 1s
  server master1 master1.ocp4.altlinux.io:6443 check inter 1s
  server master2 master2.ocp4.altlinux.io:6443 check inter 1s


frontend machine-config-server-22623
  bind *:22623
  mode tcp
  option tcplog
  default_backend machine-config-server-22623

backend machine-config-server-22623
  server bootstrap bootstrap.ocp4.altlinux.io:22623 check inter 1s
  server master0 master0.ocp4.altlinux.io:22623 check inter 1s
  server master1 master1.ocp4.altlinux.io:22623 check inter 1s
  server master2 master2.ocp4.altlinux.io:22623 check inter 1s
frontend ingress-router-443
  bind *:443
  mode tcp
  option tcplog
  default_backend ingress-router-443

backend ingress-router-443  
#  balance source
  server worker0 worker0.ocp4.altlinux.io:443 check inter  1s
  server worker1 worker1.ocp4.altlinux.io:443 check inter  1s

frontend ingress-router-80
  bind *:80
  mode tcp
  option tcplog
  default_backend ingress-router-80

backend ingress-router-80  
#  balance source
  server worker0 worker0.ocp4.altlinux.io:443 check inter 1s
  server worker1 worker1.ocp4.altlinux.io:443 check inter 1s

```


## Генерация SSH-ключей

## Установка дополнительного ПО 

## Установка openshift

### Создание ignition-файлов

#### Создание файла конфигурации install-config.yaml

```
apiVersion: v1
baseDomain: altlinux.io
compute:
- name: worker
  replicas: 0
controlPlane:
  name: master
  replicas: 1
metadata:
  name: openshift
networking:
  networkType: OVNKubernetes
  clusterNetwork:
  - cidr: 10.244.0.0/16
    hostPrefix: 24
  serviceNetwork:
  - 10.96.0.0/16
platform:
  none: {}
pullSecret: ...
```

#### Генерация файлов манифестов

```
# mkdir ocp
# cp install-config.yaml ocp
INFO Consuming Install Config from target directory 
WARNING Making control-plane schedulable by setting MastersSchedulable to true for Scheduler cluster settings 
INFO Manifests created in: ocp/manifests and ocp/openshift
```
![Создание манифестов](./Images/openshift_altcos_manifests.png)

##### Добавление манифестов (создание BTRFS томов)

После генерации манифестов они помещаются в каталоги
- `ocp/openshift/` - манифесты конфигурации серверов;
- `ocp/manifests/` - манифесты конфигурации кластера.

При конфигурации серверов выполняются следующие скрипты из каталога `ocp/openshift/`:
- `99_kubeadmin-password-secret.yaml
- `99_openshift-cluster-api_master-user-data-secret.yaml`;
- `99_openshift-cluster-api_worker-user-data-secret.yaml`;
- `99_openshift-machineconfig_99-master-ssh.yaml`;
- `99_openshift-machineconfig_99-worker-ssh.yaml`;
- `openshift-install-manifests.yaml`.

Для выполнения дополнительных действий необходимо сформировать манифесты с префиксом менее `99`, которые обеспечивают
вызов `ignition` с передачей ему `ignition`-конфигурации. 
Например для создания партиции, на которую будет монтированы каталоги docker, containers создается butane-файл
`ocp/98-var-partition.bu`:
```
variant: openshift
version: 4.9.0
metadata:
  labels:
    machineconfiguration.openshift.io/role: worker
  name: 98-var-partition
storage:
 disks:
   - device: /dev/sdb # создадим на диске /dev/sdb партицию /dev/sdb1
     wipe_table: true
     partitions:
       - number: 1
         label: docker
 filesystems:
   - device: /dev/sdb1 # создадим в партиции /dev/sdb1 файловую систему BTRFS
     format: btrfs
     wipe_filesystem: true
     label: docker
     with_mount_unit: false
 directories:
   - path: /var/mnt/docker # создадим каталог монтирования тома
     overwrite: true
 files:
   - path: /etc/fstab # добавим строку монтирования btrfs-тома на каталог /var/mnt/docker
     append:
       - inline: |
           LABEL=docker /var/mnt/docker btrfs defaults 0 2
           /var/mnt/docker/docker/ /var/lib/docker none bind 0 0
           /var/mnt/docker/containers/ /var/lib/containers/ none bind 0 0
   # заменим в конфигурации dockerd-демона:
   # тип storage-driver с overlay2 на btrfs
   - path: /etc/docker/daemon.json
     overwrite: true
     contents:
       inline: |
         {
         "init-path": "/usr/bin/tini",
         "userland-proxy-path": "/usr/bin/docker-proxy",
         "default-runtime": "docker-runc",
         "live-restore": false,
         "log-driver": "journald",
         "runtimes": {
           "docker-runc": {
             "path": "/usr/bin/runc"
           }
         },
         "default-ulimits": {
           "nofile": {
           "Name": "nofile",
           "Hard": 64000,
           "Soft": 64000
           }
         },
         "data-root": "/var/lib/docker/",
         "storage-driver": "btrfs"
         }
   # заменим в конфигурации CRI-O тип driver с overlay на btrfs
   - path: /etc/crio/crio.conf.d/00-btrfs.conf
     overwrite: true
     contents:
       inline: |
         [crio]
         root = "/var/lib/containers/storage"
         runroot = "/var/run/containers/storage"
         storage_driver = "btrfs"
         storage_option = []
         [crio.runtime]
         conmon = "/usr/bin/conmon"
         [crio.network]
         plugin_dirs = [
           "/usr/libexec/cni",
           "/opt/cni/bin/"
         ]
   # заменим в конфигурации podman тип driver с overlay2 на btrfs
   - path: /etc/containers/storage.conf
     overwrite: true
     contents:
       inline: |
         [storage]
         driver = "btrfs"
         runroot = "/var/run/containers/storage"
         graphroot = "/var/lib/containers/storage"
         [storage.options]
         additionalimagestores = [
         ]
         [storage.options.overlay]
         mountopt = "nodev,metacopy=on"
    # исключим определение flannel-подсети в CRIO 
    - path: /etc/cni/net.d/100-crio-bridge.conf
      overwrite: true
      contents:
        inline: |
          {"type": "bridge"}
```

Конвертация производится командой:
```
# butane ocp/98-var-partition.bu -o ocp/openshift/98-var-partition.yaml
```


#### Создание ignition-файлов

```
# ./openshift-install create ignition-configs --dir ocp
INFO Consuming Worker Machines from target directory 
INFO Consuming Common Manifests from target directory 
INFO Consuming OpenShift Install (Manifests) from target directory 
INFO Consuming Openshift Manifests from target directory 
INFO Consuming Master Machines from target directory 
INFO Ignition-Configs created in: ocp and ocp/auth 
```
![Создание манифестов](./Images/openshift_altcos_ignition.png)





## Ссылки

- [Running Openshift at Home - Part 4/4 Deploying Openshift 4 on Proxmox VE ](https://blog.rossbrigoli.com/2020/11/running-openshift-at-home-part-44.html)
- [Install OpenShift on any x86_64 platform with user-provisioned infrastructure](https://console.redhat.com/openshift/install/platform-agnostic)
- [Installing a cluster on any platform](https://docs.openshift.com/container-platform/4.9/installing/installing_platform_agnostic/installing-platform-agnostic.html)
- [1.1.11.3.2. Disk partitioning](https://access.redhat.com/documentation/en-us/openshift_container_platform/4.6/html-single/installing_on_bare_metal/index)
