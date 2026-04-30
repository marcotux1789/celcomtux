FROM php:8.2-apache

# 1. Actualizar e instalar curl y otras herramientas básicas PRIMERO
RUN apt-get update && apt-get install -y \
    curl \
    gnupg \
    unixodbc-dev \
    && rm -rf /var/lib/apt/lists/*

# 2. Ahora que curl está instalado, agregamos el repositorio de Microsoft
RUN curl -sSL https://packages.microsoft.com/keys/microsoft.asc | apt-key add - && \
    curl -sSL https://packages.microsoft.com/config/debian/11/prod.list -o /etc/apt/sources.list.d/mssql-release.list

# 3. Instalar el driver ODBC (ahora sí tenemos el repositorio)
RUN apt-get update && ACCEPT_EULA=Y apt-get install -y msodbcsql17

# 4. Instalar las extensiones de PHP para SQL Server
RUN pecl install sqlsrv pdo_sqlsrv && \
    docker-php-ext-enable sqlsrv pdo_sqlsrv

# 5. Copiar la aplicación
COPY . /var/www/html/

EXPOSE 80
