DB_POD=$(kubectl get pod -l io.kompose.service=db -o jsonpath="{.items[0].metadata.name}")
kubectl cp dbclean.sql "$DB_POD":/tmp/
kubectl exec -it "$DB_POD" -- sh -c 'mysql -p$MARIADB_ROOT_PASSWORD obdb < /tmp/dbclean.sql'