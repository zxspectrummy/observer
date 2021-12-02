#!/bin/sh -e
PROJECT_VERSION=$(cat VERSION)
(cd app && docker build -t observer:${PROJECT_VERSION} .)

kubectl apply -f ./kubernetes/

kubectl set image deployment.v1.apps/app app=observer:${PROJECT_VERSION}
kubectl rollout status deployment.v1.apps/app

APP_POD=$(kubectl get pod -l io.kompose.service=app -o jsonpath="{.items[0].metadata.name}")
echo "The app pod name: $APP_POD, use
  export APP_POD=$APP_POD
for easy referring to the pod, e.g.
  kubectl port-forward \$APP_POD 8080:80"
