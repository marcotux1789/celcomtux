FROM php:8.2-apache

# Instalar dependencias del sistema para SQL Server
RUN apt-get update && apt-get install -y \
    gnupg \
    unixodbc-dev \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Agregar el repositorio de Microsoft
RUN curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add - && \
    curl https://packages.microsoft.com/config/debian/11/prod.list > /etc/apt/sources.list.d/mssql-release.list

# Instalar el driver ODBC de SQL Server
RUN apt-get update && ACCEPT_EULA=Y apt-get install -y msodbcsql17

# Instalar las extensiones sqlsrv y pdo_sqlsrv con PECL
RUN pecl install sqlsrv pdo_sqlsrv && \
    docker-php-ext-enable sqlsrv pdo_sqlsrv

# Copiar los archivos de la aplicación
COPY . /var/www/html/

# Exponer el puerto
EXPOSE 80
