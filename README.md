# KSO / Better Stack Integration

## Configuration
* KSO_BASE_URL
  * Base url of your [KSO application](https://github.com/4spacesdk/kubernetes-service-orchestrator)
* KSO_CLIENT_ID
  * Created in KSO
* KSO_CLIENT_SECRET: furht59
  * Created in KSO
* KSO_DEPLOYMENT_LABEL_NAME
  * Use labels to filter deployments
  * For example: betterstack
* KSO_DEPLOYMENT_LABEL_VALUE
  * For example: enabled
* BETTERSTACK_API_TOKEN
  * Create in Better Stack
* BETTERSTACK_MONITOR_GROUP_NAME
  * This application with create a new group with this name

## Install kso
### Create `values.yaml` file
For a complete set of options see [link](https://github.com/4spacesdk/helm-charts/blob/master/charts/kso-betterstack-integration/values.yaml)
```
deployment:

  env:
    - name: KSO_BASE_URL
      value: ""
    - name: KSO_CLIENT_ID
      value: ""
    - name: KSO_CLIENT_SECRET
      value: ""
    - name: KSO_DEPLOYMENT_LABEL_NAME
      value: betterstack
    - name: KSO_DEPLOYMENT_LABEL_VALUE
      value: enabled
    - name: BETTERSTACK_API_TOKEN
      value: ""
    - name: BETTERSTACK_MONITOR_GROUP_NAME
      value: ""

resources:
  limits:
    cpu: 300m
    memory: 256Mi
  requests:
   cpu: 10m
   memory: 64Mi
```

```
### Install
```
helm upgrade --install kso 4spacesdk/kso-betterstack-integration --values=values.yaml --namespace kso --create-namespace
```

### Upgrade
```
helm repo update
helm upgrade --install kso 4spacesdk/kso-betterstack-integration --values=values.yaml --namespace kso
```

### Delete kso
```
helm delete kso
```
